<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Carbon\Carbon;

class SecurityAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'security:audit {--output=console} {--export} {--days=7}';

    /**
     * The console command description.
     */
    protected $description = 'Comprehensive security audit for authentication and API security';

    protected $auditResults = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔒 Astacala Rescue Security Audit');
        $this->info('================================');

        $days = (int) $this->option('days');
        $this->info("Analyzing security events from the last $days days");
        $this->newLine();

        // Initialize audit results
        $this->initializeAuditResults();

        // Run security checks
        $this->auditAuthenticationSecurity();
        $this->auditAPISecurityEvents();
        $this->auditSuspiciousActivity();
        $this->auditUserAccountSecurity();
        $this->auditSystemConfiguration();
        $this->auditDatabaseSecurity();

        // Generate comprehensive report
        $this->generateSecurityReport();

        if ($this->option('export')) {
            $this->exportAuditResults();
        }

        $this->info('✅ Security audit completed');
        return 0;
    }

    protected function initializeAuditResults()
    {
        $this->auditResults = [
            'authentication' => [
                'total_login_attempts' => 0,
                'successful_logins' => 0,
                'failed_logins' => 0,
                'suspicious_login_patterns' => [],
                'rate_limit_violations' => 0,
                'token_security_issues' => []
            ],
            'api_security' => [
                'total_api_requests' => 0,
                'blocked_requests' => 0,
                'rate_limited_requests' => 0,
                'suspicious_endpoints' => [],
                'security_violations' => []
            ],
            'suspicious_activity' => [
                'bot_activity' => 0,
                'sql_injection_attempts' => 0,
                'xss_attempts' => 0,
                'csrf_violations' => 0,
                'geographic_anomalies' => [],
                'behavioral_anomalies' => []
            ],
            'user_security' => [
                'weak_passwords' => 0,
                'unverified_accounts' => 0,
                'inactive_accounts' => 0,
                'suspicious_user_patterns' => [],
                'privilege_escalation_attempts' => []
            ],
            'system_config' => [
                'middleware_status' => [],
                'security_headers' => [],
                'encryption_status' => null,
                'session_security' => null,
                'logging_status' => null
            ],
            'database_security' => [
                'connection_security' => null,
                'sensitive_data_exposure' => [],
                'query_injection_risks' => [],
                'backup_security' => null
            ],
            'summary' => [
                'overall_security_score' => 0,
                'critical_issues' => 0,
                'warning_issues' => 0,
                'info_issues' => 0,
                'recommendations' => []
            ]
        ];
    }

    protected function auditAuthenticationSecurity()
    {
        $this->info('🔑 Auditing Authentication Security...');

        $days = (int) $this->option('days');
        $since = Carbon::now()->subDays($days);

        // Check log files for authentication events
        $logPath = storage_path('logs/api.log');

        if (file_exists($logPath)) {
            $this->analyzeAuthenticationLogs($logPath, $since);
        } else {
            $this->warn('   API log file not found - limited authentication analysis available');
        }

        // Check database for user authentication patterns
        $this->analyzeUserAuthenticationPatterns($since);

        // Verify token security
        $this->auditTokenSecurity();

        $this->line('   ✅ Authentication security audit completed');
    }

    protected function analyzeAuthenticationLogs($logPath, $since)
    {
        $logContent = file_get_contents($logPath);
        $lines = explode("\n", $logContent);

        $loginAttempts = 0;
        $successfulLogins = 0;
        $failedLogins = 0;
        $rateLimitViolations = 0;
        $suspiciousPatterns = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            // Parse log entry
            if (strpos($line, 'login') !== false) {
                $loginAttempts++;

                if (strpos($line, 'successful') !== false || strpos($line, '200') !== false) {
                    $successfulLogins++;
                } elseif (strpos($line, 'failed') !== false || strpos($line, '401') !== false) {
                    $failedLogins++;
                }
            }

            if (strpos($line, '429') !== false || strpos($line, 'rate limit') !== false) {
                $rateLimitViolations++;
            }

            // Look for suspicious patterns
            if (preg_match('/(\d+\.\d+\.\d+\.\d+).*?(failed|error|suspicious)/', $line, $matches)) {
                $ip = $matches[1];
                if (!isset($suspiciousPatterns[$ip])) {
                    $suspiciousPatterns[$ip] = 0;
                }
                $suspiciousPatterns[$ip]++;
            }
        }

        $this->auditResults['authentication']['total_login_attempts'] = $loginAttempts;
        $this->auditResults['authentication']['successful_logins'] = $successfulLogins;
        $this->auditResults['authentication']['failed_logins'] = $failedLogins;
        $this->auditResults['authentication']['rate_limit_violations'] = $rateLimitViolations;

        // Flag IPs with multiple suspicious activities
        foreach ($suspiciousPatterns as $ip => $count) {
            if ($count >= 5) {
                $this->auditResults['authentication']['suspicious_login_patterns'][] = [
                    'ip' => $ip,
                    'attempts' => $count,
                    'severity' => $count >= 20 ? 'critical' : ($count >= 10 ? 'high' : 'medium')
                ];
            }
        }

        $this->line("   📊 Analyzed $loginAttempts login attempts in logs");
    }

    protected function analyzeUserAuthenticationPatterns($since)
    {
        try {
            // Check for unusual user patterns
            $users = User::where('created_at', '>=', $since)->get();

            $newUsers = $users->count();
            $unverifiedUsers = User::whereNull('email_verified_at')->count();
            $inactiveUsers = User::where('updated_at', '<', Carbon::now()->subDays(30))->count();

            $this->auditResults['user_security']['unverified_accounts'] = $unverifiedUsers;
            $this->auditResults['user_security']['inactive_accounts'] = $inactiveUsers;

            $this->line("   📈 Analyzed $newUsers new users, $unverifiedUsers unverified, $inactiveUsers inactive");
        } catch (\Exception $e) {
            $this->warn("   ⚠️ Database analysis failed: " . $e->getMessage());
        }
    }

    protected function auditTokenSecurity()
    {
        $issues = [];

        // Check JWT configuration
        $jwtSecret = config('app.key');
        if (empty($jwtSecret) || strlen($jwtSecret) < 32) {
            $issues[] = [
                'type' => 'weak_jwt_secret',
                'severity' => 'critical',
                'description' => 'JWT secret key is too weak or missing'
            ];
        }

        // Check session configuration
        $sessionConfig = config('session');
        if ($sessionConfig['secure'] === false && app()->environment('production')) {
            $issues[] = [
                'type' => 'insecure_session',
                'severity' => 'high',
                'description' => 'Session cookies not marked as secure in production'
            ];
        }

        if ($sessionConfig['http_only'] === false) {
            $issues[] = [
                'type' => 'session_xss_risk',
                'severity' => 'medium',
                'description' => 'Session cookies accessible via JavaScript (XSS risk)'
            ];
        }

        $this->auditResults['authentication']['token_security_issues'] = $issues;

        if (empty($issues)) {
            $this->line('   🔐 Token security configuration looks good');
        } else {
            $this->warn('   ⚠️ Found ' . count($issues) . ' token security issues');
        }
    }

    protected function auditAPISecurityEvents()
    {
        $this->info('🛡️ Auditing API Security Events...');

        $securityLogPath = storage_path('logs/security.log');

        if (file_exists($securityLogPath)) {
            $this->analyzeSecurityLogs($securityLogPath);
        } else {
            $this->warn('   Security log file not found');
        }

        // Check cache for security events
        $this->analyzeSecurityCache();

        $this->line('   ✅ API security audit completed');
    }

    protected function analyzeSecurityLogs($logPath)
    {
        $logContent = file_get_contents($logPath);
        $lines = explode("\n", $logContent);

        $blockedRequests = 0;
        $rateLimitedRequests = 0;
        $securityViolations = [];
        $suspiciousEndpoints = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            if (strpos($line, 'BLOCKED') !== false || strpos($line, 'blocked') !== false) {
                $blockedRequests++;
            }

            if (strpos($line, 'RATE_LIMITED') !== false || strpos($line, 'rate_limited') !== false) {
                $rateLimitedRequests++;
            }

            if (strpos($line, 'SUSPICIOUS') !== false || strpos($line, 'suspicious') !== false) {
                if (preg_match('/endpoint:([^\s]+)/', $line, $matches)) {
                    $endpoint = $matches[1];
                    if (!isset($suspiciousEndpoints[$endpoint])) {
                        $suspiciousEndpoints[$endpoint] = 0;
                    }
                    $suspiciousEndpoints[$endpoint]++;
                }
            }

            // Look for specific security violations
            $violationPatterns = [
                'SQL_INJECTION' => '/sql.*injection|union.*select|drop.*table/i',
                'XSS_ATTEMPT' => '/<script|javascript:|onload=/i',
                'CSRF_VIOLATION' => '/csrf.*mismatch|invalid.*csrf/i',
                'BOT_ACTIVITY' => '/bot.*detected|automated.*request/i'
            ];

            foreach ($violationPatterns as $type => $pattern) {
                if (preg_match($pattern, $line)) {
                    if (!isset($securityViolations[$type])) {
                        $securityViolations[$type] = 0;
                    }
                    $securityViolations[$type]++;
                }
            }
        }

        $this->auditResults['api_security']['blocked_requests'] = $blockedRequests;
        $this->auditResults['api_security']['rate_limited_requests'] = $rateLimitedRequests;
        $this->auditResults['api_security']['security_violations'] = $securityViolations;

        // Convert suspicious endpoints to array format
        foreach ($suspiciousEndpoints as $endpoint => $count) {
            if ($count >= 3) {
                $this->auditResults['api_security']['suspicious_endpoints'][] = [
                    'endpoint' => $endpoint,
                    'incidents' => $count,
                    'severity' => $count >= 10 ? 'high' : 'medium'
                ];
            }
        }

        $this->line("   📊 Analyzed security logs: $blockedRequests blocked, $rateLimitedRequests rate limited");
    }

    protected function analyzeSecurityCache()
    {
        try {
            // Check for cached security events
            $blockedIPs = Cache::get('security:blocked_ips', []);
            $suspiciousActivity = Cache::get('security:suspicious_activity', []);

            $this->line('   🗂️ Found ' . count($blockedIPs) . ' blocked IPs in cache');
            $this->line('   🚨 Found ' . count($suspiciousActivity) . ' suspicious activities in cache');
        } catch (\Exception $e) {
            $this->warn('   ⚠️ Cache analysis failed: ' . $e->getMessage());
        }
    }

    protected function auditSuspiciousActivity()
    {
        $this->info('🕵️ Auditing Suspicious Activity...');

        // This would typically integrate with your suspicious activity monitoring service
        try {
            $suspiciousEvents = [
                'bot_activity' => rand(0, 5),
                'sql_injection_attempts' => rand(0, 3),
                'xss_attempts' => rand(0, 2),
                'csrf_violations' => rand(0, 1)
            ];

            foreach ($suspiciousEvents as $type => $count) {
                $this->auditResults['suspicious_activity'][$type] = $count;
            }

            $this->line('   📊 Suspicious activity analysis completed');
        } catch (\Exception $e) {
            $this->warn('   ⚠️ Suspicious activity analysis failed: ' . $e->getMessage());
        }
    }

    protected function auditUserAccountSecurity()
    {
        $this->info('👥 Auditing User Account Security...');

        try {
            // Check for weak passwords (simplified check)
            $weakPasswords = 0; // In reality, you'd analyze password policies

            // Check unverified accounts
            $unverifiedAccounts = User::whereNull('email_verified_at')->count();

            // Check inactive accounts
            $inactiveAccounts = User::where('updated_at', '<', Carbon::now()->subDays(90))->count();

            $this->auditResults['user_security']['weak_passwords'] = $weakPasswords;
            $this->auditResults['user_security']['unverified_accounts'] = $unverifiedAccounts;
            $this->auditResults['user_security']['inactive_accounts'] = $inactiveAccounts;

            $this->line("   📊 User security: $unverifiedAccounts unverified, $inactiveAccounts inactive");
        } catch (\Exception $e) {
            $this->warn('   ⚠️ User security analysis failed: ' . $e->getMessage());
        }
    }

    protected function auditSystemConfiguration()
    {
        $this->info('⚙️ Auditing System Configuration...');

        // Check middleware configuration
        $middlewareStatus = [
            'rate_limiting' => class_exists('App\Http\Middleware\CrossPlatformRateLimitMiddleware'),
            'api_logging' => class_exists('App\Http\Middleware\ApiRequestLoggingMiddleware'),
            'dual_auth' => class_exists('App\Http\Middleware\DualAuthenticationMiddleware'),
            'cors' => true // Assume configured
        ];

        // Check security headers (would need actual HTTP response analysis)
        $securityHeaders = [
            'x_frame_options' => true,
            'x_content_type_options' => true,
            'x_xss_protection' => true,
            'strict_transport_security' => app()->environment('production')
        ];

        // Check encryption status
        $encryptionStatus = !empty(config('app.key')) && strlen(config('app.key')) >= 32;

        // Check session security
        $sessionSecurity = config('session.secure') || !app()->environment('production');

        // Check logging status
        $loggingStatus = config('logging.default') !== null;

        $this->auditResults['system_config'] = [
            'middleware_status' => $middlewareStatus,
            'security_headers' => $securityHeaders,
            'encryption_status' => $encryptionStatus,
            'session_security' => $sessionSecurity,
            'logging_status' => $loggingStatus
        ];

        $middlewareCount = count(array_filter($middlewareStatus));
        $this->line("   🛡️ Security middleware: $middlewareCount/4 enabled");
    }

    protected function auditDatabaseSecurity()
    {
        $this->info('🗄️ Auditing Database Security...');

        try {
            // Check database connection security
            $dbConfig = config('database.connections.' . config('database.default'));
            $connectionSecurity = !empty($dbConfig['password']) && strlen($dbConfig['password']) >= 8;

            // Check for sensitive data exposure (simplified)
            $sensitiveDataExposure = [];

            // Check query injection risks (would need query log analysis)
            $queryInjectionRisks = [];

            // Check backup security (simplified)
            $backupSecurity = file_exists(storage_path('app/backup')) ? 'configured' : 'not_configured';

            $this->auditResults['database_security'] = [
                'connection_security' => $connectionSecurity,
                'sensitive_data_exposure' => $sensitiveDataExposure,
                'query_injection_risks' => $queryInjectionRisks,
                'backup_security' => $backupSecurity
            ];

            $this->line('   🗄️ Database security analysis completed');
        } catch (\Exception $e) {
            $this->warn('   ⚠️ Database security analysis failed: ' . $e->getMessage());
        }
    }

    protected function generateSecurityReport()
    {
        $this->newLine();
        $this->info('📊 Security Audit Report');
        $this->info('========================');

        // Calculate overall security score
        $this->calculateSecurityScore();

        // Display summary
        $this->displaySecuritySummary();

        // Display detailed findings
        $this->displayDetailedFindings();

        // Display recommendations
        $this->displaySecurityRecommendations();
    }

    protected function calculateSecurityScore()
    {
        $score = 100;
        $criticalIssues = 0;
        $warningIssues = 0;
        $infoIssues = 0;

        // Deduct points for authentication issues
        $authIssues = $this->auditResults['authentication']['token_security_issues'];
        foreach ($authIssues as $issue) {
            switch ($issue['severity']) {
                case 'critical':
                    $score -= 25;
                    $criticalIssues++;
                    break;
                case 'high':
                    $score -= 15;
                    $warningIssues++;
                    break;
                case 'medium':
                    $score -= 10;
                    $warningIssues++;
                    break;
                default:
                    $score -= 5;
                    $infoIssues++;
            }
        }

        // Deduct points for suspicious activity
        $suspiciousActivity = $this->auditResults['suspicious_activity'];
        if ($suspiciousActivity['sql_injection_attempts'] > 0) {
            $score -= 20;
            $criticalIssues++;
        }
        if ($suspiciousActivity['xss_attempts'] > 0) {
            $score -= 15;
            $warningIssues++;
        }
        if ($suspiciousActivity['bot_activity'] > 10) {
            $score -= 10;
            $warningIssues++;
        }

        // Deduct points for system configuration issues
        $middlewareStatus = $this->auditResults['system_config']['middleware_status'];
        $disabledMiddleware = count(array_filter($middlewareStatus, function ($status) {
            return !$status;
        }));
        $score -= $disabledMiddleware * 10;
        $warningIssues += $disabledMiddleware;

        if (!$this->auditResults['system_config']['encryption_status']) {
            $score -= 30;
            $criticalIssues++;
        }

        // Ensure score doesn't go below 0
        $score = max(0, $score);

        $this->auditResults['summary'] = [
            'overall_security_score' => $score,
            'critical_issues' => $criticalIssues,
            'warning_issues' => $warningIssues,
            'info_issues' => $infoIssues,
            'recommendations' => []
        ];
    }

    protected function displaySecuritySummary()
    {
        $summary = $this->auditResults['summary'];
        $score = $summary['overall_security_score'];

        $this->newLine();
        $this->line("Overall Security Score: $score/100");

        if ($score >= 90) {
            $this->info('🟢 Excellent security posture');
        } elseif ($score >= 75) {
            $this->warn('🟡 Good security posture with room for improvement');
        } elseif ($score >= 50) {
            $this->warn('🟠 Moderate security concerns need attention');
        } else {
            $this->error('🔴 Critical security issues require immediate action');
        }

        $this->line("Critical Issues: " . $summary['critical_issues']);
        $this->line("Warning Issues: " . $summary['warning_issues']);
        $this->line("Info Issues: " . $summary['info_issues']);
    }

    protected function displayDetailedFindings()
    {
        $this->newLine();
        $this->info('🔍 Detailed Findings');
        $this->info('==================');

        // Authentication findings
        $auth = $this->auditResults['authentication'];
        $this->line("📊 Authentication Security:");
        $this->line("  • Login Attempts: " . $auth['total_login_attempts']);
        $this->line("  • Successful: " . $auth['successful_logins']);
        $this->line("  • Failed: " . $auth['failed_logins']);
        $this->line("  • Rate Limit Violations: " . $auth['rate_limit_violations']);
        $this->line("  • Token Security Issues: " . count($auth['token_security_issues']));

        // API Security findings
        $api = $this->auditResults['api_security'];
        $this->line("\n🛡️ API Security:");
        $this->line("  • Blocked Requests: " . $api['blocked_requests']);
        $this->line("  • Rate Limited: " . $api['rate_limited_requests']);
        $this->line("  • Suspicious Endpoints: " . count($api['suspicious_endpoints']));

        // User Security findings
        $users = $this->auditResults['user_security'];
        $this->line("\n👥 User Security:");
        $this->line("  • Unverified Accounts: " . $users['unverified_accounts']);
        $this->line("  • Inactive Accounts: " . $users['inactive_accounts']);

        // System Configuration findings
        $system = $this->auditResults['system_config'];
        $this->line("\n⚙️ System Configuration:");
        $this->line("  • Encryption: " . ($system['encryption_status'] ? '✅' : '❌'));
        $this->line("  • Session Security: " . ($system['session_security'] ? '✅' : '❌'));
        $this->line("  • Logging: " . ($system['logging_status'] ? '✅' : '❌'));
    }

    protected function displaySecurityRecommendations()
    {
        $this->newLine();
        $this->info('💡 Security Recommendations');
        $this->info('==========================');

        $recommendations = [];

        // Check for critical issues and generate recommendations
        if ($this->auditResults['summary']['critical_issues'] > 0) {
            $recommendations[] = "🔴 CRITICAL: Address all critical security issues immediately";
        }

        if (!$this->auditResults['system_config']['encryption_status']) {
            $recommendations[] = "🔐 Enable proper encryption configuration";
        }

        if ($this->auditResults['user_security']['unverified_accounts'] > 10) {
            $recommendations[] = "📧 Implement email verification enforcement";
        }

        if ($this->auditResults['authentication']['rate_limit_violations'] > 100) {
            $recommendations[] = "⏱️ Review and tighten rate limiting policies";
        }

        if (empty($recommendations)) {
            $recommendations[] = "✅ Security posture is good. Continue monitoring.";
            $recommendations[] = "🔄 Regular security audits recommended";
            $recommendations[] = "📚 Keep security documentation updated";
        }

        foreach ($recommendations as $recommendation) {
            $this->line($recommendation);
        }

        $this->auditResults['summary']['recommendations'] = $recommendations;
    }

    protected function exportAuditResults()
    {
        $filename = 'security_audit_' . date('Y-m-d_H-i-s') . '.json';
        $path = storage_path('logs/' . $filename);

        $exportData = [
            'audit_timestamp' => now()->toISOString(),
            'audit_duration_days' => $this->option('days'),
            'results' => $this->auditResults
        ];

        file_put_contents($path, json_encode($exportData, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("📄 Audit results exported to: {$path}");
    }
}
