<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Services\GibranWebAppAdapter;
use App\Http\Services\CrossPlatformDataMapper;
use App\Http\Services\CrossPlatformValidator;
use App\Models\DisasterReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Gibran Web App Compatibility Controller
 * Provides API endpoints that maintain compatibility with Gibran's web application
 * while integrating with the unified mobile backend system
 */
class GibranWebCompatibilityController extends Controller
{
    protected GibranWebAppAdapter $gibranAdapter;
    protected CrossPlatformDataMapper $dataMapper;
    protected CrossPlatformValidator $validator;

    public function __construct(
        GibranWebAppAdapter $gibranAdapter,
        CrossPlatformDataMapper $dataMapper,
        CrossPlatformValidator $validator
    ) {
        $this->gibranAdapter = $gibranAdapter;
        $this->dataMapper = $dataMapper;
        $this->validator = $validator;
    }

    /**
     * Submit disaster report from Gibran's web application
     * Endpoint: POST /api/gibran/pelaporans
     * 
     * Maintains compatibility with Gibran's existing form structure
     */
    public function submitPelaporan(Request $request): JsonResponse
    {
        try {
            Log::info('Gibran Web App: Pelaporan submission received', [
                'data_keys' => array_keys($request->all()),
                'user_id' => Auth::id(),
            ]);

            // Validate Gibran's web form data
            $validatedData = $this->validateGibranSubmission($request->all());

            // Transform Gibran's format to unified backend format
            $unifiedData = $this->gibranAdapter->transformGibranToUnifiedBackend($validatedData);

            // Create the disaster report in mobile backend
            $report = DisasterReport::create($unifiedData);

            // Handle file uploads if present
            if ($request->hasFile('foto_lokasi_bencana')) {
                $this->handleGibranImageUpload($request->file('foto_lokasi_bencana'), $report);
            }

            // Transform response back to Gibran's expected format
            $gibranResponse = $this->gibranAdapter->transformGibranToMobileFormat($report);

            Log::info('Gibran Web App: Pelaporan created successfully', [
                'report_id' => $report->id,
                'title' => $report->title,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Laporan bencana berhasil dikirim',
                'data' => $gibranResponse,
                'pelaporan_id' => $report->id,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Gibran Web App: Validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak valid',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Gibran Web App: Submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim laporan bencana',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get disaster reports for Gibran's admin dashboard
     * Endpoint: GET /api/gibran/pelaporans
     * 
     * Returns data formatted for Gibran's web dashboard display
     */
    public function getPelaporans(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $status = $request->get('status');
            $verified = $request->get('verified');

            // Build query for disaster reports
            $query = DisasterReport::with(['reporter', 'images']);

            // Apply Gibran-specific filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($verified !== null) {
                $verificationStatus = $verified === 'true' || $verified === '1';
                if ($verificationStatus) {
                    $query->whereIn('status', ['VERIFIED', 'ACTIVE']);
                } else {
                    $query->where('status', 'PENDING');
                }
            }

            $reports = $query->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            // Transform each report to format expected by web dashboard
            $transformedReports = $reports->getCollection()->map(function ($report) {
                $metadata = is_string($report->metadata) ? json_decode($report->metadata, true) : $report->metadata;
                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'description' => $report->description,
                    'disaster_type' => $report->disaster_type,
                    'severity_level' => $report->severity_level,
                    'team_name' => $report->team_name ?? '',
                    'status' => $report->status,
                    'latitude' => $report->latitude,
                    'longitude' => $report->longitude,
                    'location_name' => $report->location_name,
                    'coordinate_display' => $report->coordinate_display ?? '',
                    'incident_timestamp' => $report->incident_timestamp,
                    'reporter_phone' => $report->reporter_phone ?? '',
                    'reporter_username' => $report->reporter_username ?? '',
                    'personnel_count' => $report->personnel_count ?? 0,
                    'casualties' => $report->casualty_count ?? 0,
                    'platform_info' => [
                        'source' => $metadata['source_platform'] ?? 'web',
                        'submission_method' => $metadata['submission_method'] ?? 'unknown'
                    ],
                    'created_at' => $report->created_at,
                    'updated_at' => $report->updated_at,
                ];
            })->toArray();

            return response()->json([
                'status' => 'success',
                'message' => 'Data pelaporan berhasil diambil',
                'data' => $transformedReports,
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                    'from' => $reports->firstItem(),
                    'to' => $reports->lastItem(),
                ],
                'statistics' => $this->getGibranDashboardStats(),
            ]);
        } catch (\Exception $e) {
            Log::error('Gibran Web App: Failed to get pelaporans', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data pelaporan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify/approve disaster report from Gibran's admin panel
     * Endpoint: POST /api/gibran/pelaporans/{id}/verify
     */
    public function verifyPelaporan(Request $request, int $id): JsonResponse
    {
        try {
            $report = DisasterReport::findOrFail($id);

            $validatedData = Validator::make($request->all(), [
                'status_verifikasi' => 'required|boolean',
                'catatan_admin' => 'nullable|string|max:1000',
                'status_notifikasi' => 'boolean',
            ])->validate();

            // Update report status
            $report->update([
                'status' => $validatedData['status_verifikasi'] ? 'VERIFIED' : 'PENDING',
                'verification_notes' => $validatedData['catatan_admin'] ?? null,
                'verified_by_admin_id' => Auth::id(),
                'verified_at' => $validatedData['status_verifikasi'] ? now() : null,
            ]);

            // Update notification status if provided
            if (isset($validatedData['status_notifikasi'])) {
                $report->update(['notification_sent' => $validatedData['status_notifikasi']]);
            }

            Log::info('Gibran Web App: Report verified', [
                'report_id' => $report->id,
                'verified' => $validatedData['status_verifikasi'],
                'admin_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Status verifikasi berhasil diperbarui',
                'data' => $this->gibranAdapter->transformGibranToMobileFormat($report),
            ]);
        } catch (\Exception $e) {
            Log::error('Gibran Web App: Verification failed', [
                'report_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui status verifikasi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard statistics for Gibran's admin panel
     * Endpoint: GET /api/gibran/dashboard/statistics
     */
    public function getDashboardStatistics(): JsonResponse
    {
        try {
            $stats = $this->getGibranDashboardStats();

            return response()->json([
                'status' => 'success',
                'message' => 'Statistik dashboard berhasil diambil',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Gibran Web App: Failed to get statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil statistik dashboard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle user authentication for Gibran's web app
     * Endpoint: POST /api/gibran/auth/login
     * 
     * Provides JWT tokens while maintaining session compatibility
     */
    public function webAuthLogin(Request $request): JsonResponse
    {
        try {
            $validatedData = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
                'remember' => 'boolean',
            ])->validate();

            if (!Auth::attempt($validatedData)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email atau password salah',
                ], 401);
            }

            $user = Auth::user();
            /** @var \App\Models\User $user */

            // Generate JWT token for API access (using Sanctum)
            $token = $user->createToken('gibran_web_access', ['*'], now()->addDays(7))->plainTextToken;

            Log::info('Gibran Web App: User login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Login berhasil',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ?? 'admin',
                    ],
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Gibran Web App: Login failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Login gagal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get published disaster news for public endpoint
     * Endpoint: GET /api/gibran/berita-bencana
     * 
     * Maintains compatibility with Gibran's public API
     */
    public function getBeritaBencana(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);

            // Get verified reports that can be published as news
            $reports = DisasterReport::with(['reporter', 'images'])
                ->where('status', 'VERIFIED')
                ->orWhere('status', 'PUBLISHED')
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            // Transform to Gibran's berita format
            $beritaData = $reports->items();
            $transformedBerita = array_map(function ($report) {
                return [
                    'id' => $report->id,
                    'pblk_judul_bencana' => $report->title,
                    'pblk_lokasi_bencana' => $report->location_name,
                    'pblk_titik_kordinat_bencana' => "{$report->latitude},{$report->longitude}",
                    'pblk_skala_bencana' => $this->mapSeverityToGibranScale($report->severity_level),
                    'deskripsi_umum' => $report->description,
                    'pblk_foto_bencana' => $report->images ? $report->images->first()?->image_path : null,
                    'created_at' => $report->created_at->toISOString(),
                    'updated_at' => $report->updated_at->toISOString(),
                ];
            }, $beritaData);

            return response()->json([
                'status' => 'success',
                'message' => 'Berita bencana berhasil diambil',
                'data' => $transformedBerita,
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'total' => $reports->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Gibran Web App: Failed to get berita bencana', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil berita bencana',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get real publications from publications table
     * Endpoint: GET /api/gibran/publications
     * 
     * Returns data formatted for Gibran's web dashboard display
     */
    public function getPublications(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $status = $request->get('status', 'published');
            $type = $request->get('type');

            // Build query for publications
            $query = \App\Models\Publication::with(['author']);

            // Apply Gibran-specific filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($type) {
                $query->where('type', $type);
            }

            $publications = $query->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            // Transform each publication to format expected by web dashboard
            $transformedPublications = $publications->getCollection()->map(function ($publication) {
                return [
                    'id' => $publication->id,
                    'title' => $publication->title,
                    'content' => $publication->content,
                    'type' => $publication->type,
                    'category' => $publication->category,
                    'status' => $publication->status,
                    'created_by' => $publication->created_by ?? '',
                    'creator_name' => $publication->creator_name ?? '',
                    'author_id' => $publication->author_id,
                    'author_name' => $publication->author ? $publication->author->name : '',
                    'published_at' => $publication->published_at,
                    'created_at' => $publication->created_at,
                    'updated_at' => $publication->updated_at,
                ];
            })->toArray();

            return response()->json([
                'status' => 'success',
                'message' => 'Data publikasi berhasil diambil',
                'data' => $transformedPublications,
                'pagination' => [
                    'current_page' => $publications->currentPage(),
                    'last_page' => $publications->lastPage(),
                    'per_page' => $publications->perPage(),
                    'total' => $publications->total(),
                    'from' => $publications->firstItem(),
                    'to' => $publications->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Gibran Web App: Failed to get publications', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data publikasi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate Gibran's form submission
     */
    private function validateGibranSubmission(array $data): array
    {
        return Validator::make($data, [
            'nama_team_pelapor' => 'required|string|max:255',
            'jumlah_personel' => 'required|integer|min:1',
            'no_handphone' => 'required|string|max:20',
            'informasi_singkat_bencana' => 'required|string|max:500',
            'lokasi_bencana' => 'required|string|max:255',
            'titik_kordinat_lokasi_bencana' => 'nullable|string',
            'skala_bencana' => 'required|string',
            'jumlah_korban' => 'nullable|integer|min:0',
            'deskripsi_terkait_data_lainya' => 'nullable|string',
            'foto_lokasi_bencana' => 'nullable|file|image|max:5120', // 5MB max
            'bukti_surat_perintah_tugas' => 'nullable|file|max:10240', // 10MB max
        ])->validate();
    }

    /**
     * Handle image upload from Gibran's form
     */
    private function handleGibranImageUpload($file, DisasterReport $report): void
    {
        if ($file && $file->isValid()) {
            $path = $file->store('disaster-reports', 'public');

            $report->images()->create([
                'image_path' => $path,
                'is_primary' => true,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    /**
     * Get dashboard statistics formatted for Gibran's web app
     */
    private function getGibranDashboardStats(): array
    {
        return [
            'total_pelaporan' => DisasterReport::count(),
            'pelaporan_pending' => DisasterReport::where('status', 'PENDING')->count(),
            'pelaporan_verified' => DisasterReport::where('status', 'VERIFIED')->count(),
            'pelaporan_hari_ini' => DisasterReport::whereDate('created_at', today())->count(),
            'total_korban' => DisasterReport::sum('estimated_affected'),
            'breakdown_skala' => [
                'ringan' => DisasterReport::where('severity_level', 'low')->count(),
                'sedang' => DisasterReport::where('severity_level', 'medium')->count(),
                'tinggi' => DisasterReport::where('severity_level', 'high')->count(),
                'kritis' => DisasterReport::where('severity_level', 'critical')->count(),
            ],
            'pelaporan_terbaru' => DisasterReport::with('reporter')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($report) {
                    return [
                        'id' => $report->id,
                        'judul' => $report->title,
                        'lokasi' => $report->location_name,
                        'pelapor' => $report->reporter->name ?? 'Unknown',
                        'waktu' => $report->created_at->diffForHumans(),
                    ];
                }),
        ];
    }

    /**
     * Map severity level to Gibran's scale format
     */
    private function mapSeverityToGibranScale(string $severity): string
    {
        $mapping = [
            'low' => 'Ringan',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'critical' => 'Kritis',
        ];

        return $mapping[$severity] ?? 'Sedang';
    }

    /**
     * Delete disaster report (Admin action)
     * Endpoint: DELETE /api/gibran/pelaporans/{id}
     */
    public function deletePelaporan($id): JsonResponse
    {
        try {
            Log::info('Gibran Web App: Delete pelaporan requested', [
                'report_id' => $id,
                'admin_id' => Auth::id(),
            ]);

            $report = DisasterReport::find($id);

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pelaporan tidak ditemukan'
                ], 404);
            }

            // Delete associated files if any
            if ($report->images()->exists()) {
                foreach ($report->images as $image) {
                    // Delete physical file
                    if (file_exists(storage_path('app/public/' . $image->file_path))) {
                        unlink(storage_path('app/public/' . $image->file_path));
                    }
                    $image->delete();
                }
            }

            // Delete the report
            $report->delete();

            Log::info('Gibran Web App: Pelaporan deleted successfully', [
                'deleted_report_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pelaporan berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            Log::error('Gibran Web App: Error deleting pelaporan', [
                'report_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pelaporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single disaster report details
     * Endpoint: GET /api/gibran/pelaporans/{id}
     */
    public function showPelaporan($id): JsonResponse
    {
        try {
            $report = DisasterReport::with(['reporter', 'images'])->find($id);

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pelaporan tidak ditemukan'
                ], 404);
            }

            $data = [
                'id' => $report->id,
                'title' => $report->title,
                'description' => $report->description,
                'location_name' => $report->location_name,
                'coordinates' => $report->coordinates,
                'coordinate_display' => $report->coordinate_display,
                'disaster_type' => $report->disaster_type,
                'severity_level' => $report->severity_level,
                'affected_population' => $report->affected_population,
                'casualties' => $report->casualties,
                'infrastructure_damage' => $report->infrastructure_damage,
                'estimated_loss' => $report->estimated_loss,
                'team_name' => $report->team_name,
                'team_size' => $report->team_size,
                'contact_phone' => $report->contact_phone,
                'reporter_phone' => $report->reporter_phone,
                'reporter_username' => $report->reporter_username,
                'status' => $report->status,
                'verification_status' => $report->verification_status,
                'verification_notes' => $report->verification_notes,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at,
                'reporter' => $report->reporter ? [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->name,
                    'username' => $report->reporter->username,
                    'email' => $report->reporter->email,
                ] : null,
                'images' => $report->images ? $report->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => asset('storage/' . $image->file_path),
                        'file_path' => $image->file_path,
                        'file_type' => $image->file_type,
                        'file_size' => $image->file_size,
                    ];
                }) : [],
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Detail pelaporan berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Gibran Web App: Error getting pelaporan detail', [
                'report_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail pelaporan'
            ], 500);
        }
    }
}
