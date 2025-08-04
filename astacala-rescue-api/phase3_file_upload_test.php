<?php

// Phase 3 File Upload Integration Test
echo "📁 Phase 3 File Upload Integration Test\n";
echo "=======================================\n\n";

// Step 1: Authenticate
echo "📱 Step 1: Authenticating user...\n";
$authResponse = authenticate();
if (!$authResponse) {
    echo "❌ Authentication failed\n";
    exit(1);
}

$token = $authResponse['data']['tokens']['accessToken'];
echo "✅ Authentication successful\n";
echo "👤 User: " . $authResponse['data']['user']['name'] . "\n\n";

// Step 2: Create test image file for upload
echo "📷 Step 2: Creating test image file...\n";
$testImagePath = createTestImage();
if (!$testImagePath) {
    echo "❌ Failed to create test image\n";
    exit(1);
}
echo "✅ Test image created: $testImagePath\n\n";

// Step 3: Submit disaster report first (required for file upload)
echo "📊 Step 3: Creating disaster report for file upload test...\n";
$reportId = submitDisasterReport($token);
if (!$reportId) {
    echo "❌ Failed to create disaster report\n";
    exit(1);
}
echo "✅ Disaster report created: ID $reportId\n\n";

// Step 4: Test file upload to disaster report
echo "📁 Step 4: Testing file upload to disaster report...\n";
$uploadResult = uploadImageToReport($token, $reportId, $testImagePath);
if (!$uploadResult) {
    echo "❌ File upload failed\n";
    cleanup($testImagePath);
    exit(1);
}
echo "✅ File upload successful\n\n";

// Step 5: Verify uploaded file appears in report
echo "🔍 Step 5: Verifying uploaded file appears in report...\n";
$fileVerified = verifyUploadedFile($token, $reportId);
if (!$fileVerified) {
    echo "❌ Uploaded file not found in report\n";
} else {
    echo "✅ Uploaded file verified in report\n";
}
echo "\n";

// Step 6: Test file retrieval
echo "🌐 Step 6: Testing file accessibility...\n";
$fileAccessible = testFileAccessibility($uploadResult);
if ($fileAccessible) {
    echo "✅ Uploaded file is accessible\n";
} else {
    echo "⚠️ Uploaded file accessibility issues\n";
}
echo "\n";

// Cleanup
cleanup($testImagePath);

// Summary
echo "🎯 File Upload Integration Test Summary\n";
echo "=====================================\n";
echo "📱 Authentication: ✅ WORKING\n";
echo "📊 Report Creation: ✅ WORKING\n";
echo "📁 File Upload: " . ($uploadResult ? "✅ WORKING" : "❌ FAILED") . "\n";
echo "🔍 File Verification: " . ($fileVerified ? "✅ WORKING" : "❌ FAILED") . "\n";
echo "🌐 File Access: " . ($fileAccessible ? "✅ WORKING" : "⚠️ NEEDS REVIEW") . "\n\n";

if ($uploadResult && $fileVerified) {
    echo "🏆 Phase 3 File Upload Integration: VALIDATED!\n";
} else {
    echo "⚠️ File Upload Integration: NEEDS FIXES\n";
}

// =============================================================================
// Helper Functions
// =============================================================================

function authenticate()
{
    $url = 'http://127.0.0.1:8000/api/v1/auth/login';
    $data = [
        'email' => 'test@astacala.com',
        'password' => 'password123'
    ];

    return makeRequest('POST', $url, $data);
}

function submitDisasterReport($token)
{
    $url = 'http://127.0.0.1:8000/api/v1/reports';
    $data = [
        'title' => 'File Upload Test Report',
        'description' => 'Testing file upload integration for Phase 3',
        'disasterType' => 'FIRE',
        'severityLevel' => 'HIGH',
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'locationName' => 'File Upload Test Location',
        'estimatedAffected' => 10,
        'teamName' => 'File Upload Test Team',
        'weatherCondition' => 'Clear',
        'incidentTimestamp' => date('Y-m-d H:i:s')
    ];

    $response = makeRequest('POST', $url, $data, $token);

    if ($response && $response['success']) {
        return $response['data']['reportId'];
    }

    return false;
}

function createTestImage()
{
    $imagePath = __DIR__ . '/test_upload_image.jpg';

    // Create a simple test image using GD
    if (extension_loaded('gd')) {
        $image = imagecreate(300, 200);
        $background = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 0, 0, 0);

        imagestring($image, 5, 50, 80, 'Phase 3 Test Image', $textColor);
        imagestring($image, 3, 60, 120, 'File Upload Integration', $textColor);

        if (imagejpeg($image, $imagePath, 80)) {
            imagedestroy($image);
            return $imagePath;
        }
        imagedestroy($image);
    }

    // Fallback: create a minimal valid JPEG file
    $jpegHeader = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00";
    $jpegData = $jpegHeader . str_repeat("\x00", 100) . "\xFF\xD9";

    if (file_put_contents($imagePath, $jpegData)) {
        return $imagePath;
    }

    return false;
}

function uploadImageToReport($token, $reportId, $imagePath)
{
    $url = "http://127.0.0.1:8000/api/v1/files/disasters/$reportId/images";

    // Use cURL for file upload
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);

    // Prepare multipart form data
    $postData = [
        'images[]' => new CURLFile($imagePath, 'image/jpeg', 'test_image.jpg'),
        'platform' => 'mobile'
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        echo "❌ File upload HTTP request failed\n";
        return false;
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && $decoded && $decoded['success']) {
        echo "📁 Upload response: " . $decoded['message'] . "\n";
        if (isset($decoded['data']['uploadedFiles'])) {
            return $decoded['data']['uploadedFiles'];
        }
        return true;
    } else {
        echo "❌ HTTP $httpCode: " . ($decoded['message'] ?? 'Upload failed') . "\n";
        if (isset($decoded['errors'])) {
            echo "📄 Errors: " . json_encode($decoded['errors']) . "\n";
        }
        return false;
    }
}

function verifyUploadedFile($token, $reportId)
{
    $url = "http://127.0.0.1:8000/api/v1/reports/$reportId";
    $response = makeRequest('GET', $url, null, $token);

    if ($response && $response['success']) {
        $report = $response['data'];
        if (isset($report['images']) && count($report['images']) > 0) {
            echo "📷 Found " . count($report['images']) . " image(s) in report\n";
            foreach ($report['images'] as $image) {
                echo "🔗 Image URL: " . $image['image_url'] . "\n";
            }
            return true;
        } else {
            echo "📷 No images found in report\n";
            return false;
        }
    }

    echo "❌ Failed to retrieve report details\n";
    return false;
}

function testFileAccessibility($uploadResult)
{
    if (!$uploadResult || !is_array($uploadResult)) {
        return false;
    }

    foreach ($uploadResult as $file) {
        if (isset($file['url'])) {
            $url = $file['url'];
            // Convert relative URL to full URL
            if (strpos($url, 'http') !== 0) {
                $url = 'http://127.0.0.1:8000' . $url;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo "🌐 File accessibility test: HTTP $httpCode for $url\n";
            return $httpCode >= 200 && $httpCode < 300;
        }
    }

    return false;
}

function cleanup($imagePath)
{
    if (file_exists($imagePath)) {
        unlink($imagePath);
        echo "🧹 Cleaned up test image file\n";
    }
}

function makeRequest($method, $url, $data = null, $token = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return false;
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return $decoded;
    } else {
        echo "❌ HTTP $httpCode: " . ($decoded['message'] ?? 'Request failed') . "\n";
        return false;
    }
}
