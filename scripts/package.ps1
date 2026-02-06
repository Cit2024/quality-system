<#
.SYNOPSIS
    Package Quality System for production deployment

.DESCRIPTION
    Creates a compressed archive of the project excluding development files,
    tests, and documentation. Ready for upload to production server.

.PARAMETER OutputDir
    Directory where the package will be created (default: ../releases)

.PARAMETER IncludeTests
    Include test files in the package (default: false)

.EXAMPLE
    .\scripts\package.ps1
    Creates quality-system-YYYY-MM-DD.zip in releases/ folder

.EXAMPLE
    .\scripts\package.ps1 -OutputDir "C:\Deploy" -IncludeTests
    Creates package with tests in C:\Deploy
#>

param(
    [string]$OutputDir = "releases",
    [switch]$IncludeTests = $false
)

# Colors for output
function Write-Success { Write-Host $args -ForegroundColor Green }
function Write-Info { Write-Host $args -ForegroundColor Cyan }
function Write-Warning { Write-Host $args -ForegroundColor Yellow }
function Write-Error { Write-Host $args -ForegroundColor Red }

# Header
Write-Info "================================================================================"
Write-Info "  Quality System - Production Package Builder"
Write-Info "================================================================================"
Write-Host ""

# Parse PHP files to find default values and populate .env
function Update-EnvFromConfig {
    param (
        [string]$PhpFile,
        [string]$EnvFile
    )

    if (-not (Test-Path $PhpFile)) { return }
    
    $content = Get-Content $PhpFile -Raw
    
    # Defaults to look for
    $patterns = @{
        'DB_HOST' = 'DB_HOST.*?:\s*"([^"]+)"'
        'DB_PORT' = 'DB_PORT.*?:\s*(\d+)\s*\)'
        'DB_USER' = 'DB_USER.*?:\s*"([^"]+)"'
        'DB_PASS' = 'DB_PASS.*?:\s*"([^"]+)"'
        'DB_NAME' = 'DB_NAME.*?:\s*"([^"]+)"'
        
        'CIT_DB_HOST' = 'CIT_DB_HOST.*?:\s*"([^"]+)"'
        'CIT_DB_PORT' = 'CIT_DB_PORT.*?:\s*(\d+)\s*\)'
        'CIT_DB_USER' = 'CIT_DB_USER.*?:\s*"([^"]+)"'
        'CIT_DB_PASS' = 'CIT_DB_PASS.*?:\s*"([^"]+)"'
        'CIT_DB_NAME' = 'CIT_DB_NAME.*?:\s*"([^"]+)"'
    }

    $envContent = ""
    if (Test-Path $EnvFile) {
        $envContent = Get-Content $EnvFile -Raw
    } else {
        New-Item -Path $EnvFile -ItemType File -Force | Out-Null
    }

    $updatesMade = $false
    
    foreach ($key in $patterns.Keys) {
        # Check if key already exists in .env
        if ($envContent -match "(?m)^$key=") { continue }

        # Try to find default in PHP file
        if ($content -match $patterns[$key]) {
            $value = $matches[1]
            Write-Info "  Found default for ${key}: $value"
            Add-Content -Path $EnvFile -Value "$key=$value"
            $updatesMade = $true
        }
    }

    if ($updatesMade) {
        Write-Success "  Updated .env with defaults from $(Split-Path $PhpFile -Leaf)"
    }
}



# Configuration
$projectRoot = Get-Location
$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$packageName = "quality-system-$timestamp"
$tempDir = Join-Path $env:TEMP $packageName
$outputPath = Join-Path $OutputDir "$packageName.zip"

# Ensure output directory exists
if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

Write-Info "Configuration:"
Write-Host "  Project Root: $projectRoot"
Write-Host "  Package Name: $packageName"
Write-Host "  Output: $outputPath"
Write-Host "  Include Tests: $IncludeTests"
Write-Host ""

# Auto-generate .env from config defaults
Write-Info "Checking for .env configuration..."
$envFile = Join-Path $projectRoot ".env"
Update-EnvFromConfig -PhpFile (Join-Path $projectRoot "config/DbConnection.php") -EnvFile $envFile
Update-EnvFromConfig -PhpFile (Join-Path $projectRoot "config/dbConnectionCit.php") -EnvFile $envFile
Write-Host ""

# Files/folders to ALWAYS exclude
$excludePatterns = @(
    # Version control
    '.git',
    '.gitignore',
    '.gitattributes',
    
    # IDE/Editor
    '.vscode',
    '.idea',
    '*.code-workspace',
    
    # Development
    'node_modules',
    '.editorconfig',
    
    # Documentation (keep only essential)
    'README.md',
    'ARCHITECTURE.md',
    'DEVELOPMENT.md',
    
    # Build artifacts
    '*.log',
    'logs/*',
    'temp/*',
    'cache/*',
    
    # Backups (don't include in package, create fresh on server)
    'backups/*',
    
    # Release directory itself
    'releases',
    
    # Packaging script
    'scripts/package.ps1',
    
    # Brain/AI artifacts
    '.gemini',
    
    # OS files
    '.DS_Store',
    'Thumbs.db',
    'desktop.ini',
    
    # Temporary files
    '*.tmp',
    '*.bak',
    '*~',
    
    # Environment variables
    '.env'
)

# Read exclusions from .packageignore if it exists
$packageIgnoreFile = Join-Path $projectRoot ".packageignore"
if (Test-Path $packageIgnoreFile) {
    Write-Info "Reading exclusions from .packageignore..."
    $customExclusions = Get-Content $packageIgnoreFile | Where-Object { 
        # Skip comments and empty lines
        $_ -notmatch '^\s*#' -and $_ -notmatch '^\s*$' 
    }
    
    foreach ($exclusion in $customExclusions) {
        # Trim whitespace
        $cleanExclusion = $exclusion.Trim()
        if (-not [string]::IsNullOrWhiteSpace($cleanExclusion)) {
            $excludePatterns += $cleanExclusion
        }
    }
    Write-Info "  Added $(($customExclusions | Measure-Object).Count) custom exclusion patterns"
}

# Add test files to exclusions if not including tests
if (-not $IncludeTests) {
    $excludePatterns += @(
        'tests',
        'phpunit.xml',
        'phpunit.xml.dist',
        '.phpunit.result.cache'
    )
}

Write-Info "Step 1: Creating temporary directory..."
if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null
Write-Success " Temporary directory created"

Write-Info "`nStep 2: Copying production files..."
$fileCount = 0
$totalSize = 0

# Get all files recursively
$allFiles = Get-ChildItem -Path $projectRoot -Recurse -File

foreach ($file in $allFiles) {
    # Get relative path
    $relativePath = $file.FullName.Substring($projectRoot.Path.Length + 1)
    
    # Check if file should be excluded
    $shouldExclude = $false
    foreach ($pattern in $excludePatterns) {
        # Convert glob pattern to regex-like matching
        if ($relativePath -like $pattern -or 
            $relativePath.StartsWith($pattern.TrimEnd('*')) -or
            $file.Name -like $pattern) {
            $shouldExclude = $true
            break
        }
    }
    
    if (-not $shouldExclude) {
        # Create destination directory structure
        $destPath = Join-Path $tempDir $relativePath
        $destDir = Split-Path $destPath -Parent
        
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        
        # Copy file
        Copy-Item $file.FullName -Destination $destPath -Force
        $fileCount++
        $totalSize += $file.Length
    }
}

Write-Success " Copied $fileCount files ($([math]::Round($totalSize / 1MB, 2)) MB)"

Write-Info "`nStep 3: Creating required directories..."
# Ensure these directories exist in the package (even if empty)
$requiredDirs = @(
    'backups/daily',
    'backups/weekly',
    'backups/monthly',
    'backups/logs',
    'logs',
    'cache',
    'temp'
)

foreach ($dir in $requiredDirs) {
    $fullPath = Join-Path $tempDir $dir
    if (-not (Test-Path $fullPath)) {
        New-Item -ItemType Directory -Path $fullPath -Force | Out-Null
    }
    
    # Create .htaccess to protect directories
    $htaccessPath = Join-Path $fullPath ".htaccess"
    if (-not (Test-Path $htaccessPath)) {
        "# Deny all access`nDeny from all" | Out-File -FilePath $htaccessPath -Encoding ASCII
    }
}
Write-Success " Required directories created"

Write-Info "`nStep 4: Creating deployment manifest..."
$manifest = @{
    'package_name' = $packageName
    'created_at' = $timestamp
    'php_version_required' = '8.0+'
    'mysql_version_required' = '5.7+'
    'file_count' = $fileCount
    'total_size_mb' = [math]::Round($totalSize / 1MB, 2)
    'includes_tests' = $IncludeTests
    'deployment_steps' = @(
        '1. Upload and extract to web directory',
        '2. Configure config/DbConnection.php with database credentials',
        '3. Set file permissions: chmod 755 for directories, 644 for files',
        '4. Make scripts executable: chmod +x scripts/*.php',
        '5. Run migrations: php scripts/migrate.php up',
        '6. Run verification: php scripts/verify.php',
        '7. Test the site',
        '8. Configure automated backups (see DEPLOYMENT.md)'
    )
}

$manifestPath = Join-Path $tempDir "MANIFEST.json"
$manifest | ConvertTo-Json -Depth 10 | Out-File -FilePath $manifestPath -Encoding UTF8
Write-Success " Manifest created"

Write-Info "`nStep 5: Creating installation guide..."
$installGuide = @"
# Quick Installation Guide

## Package Information
- Created: $timestamp
- File Count: $fileCount
- Total Size: $([math]::Round($totalSize / 1MB, 2)) MB
- Tests Included: $IncludeTests

## Installation Steps

### 1. Upload Files
Upload all files to your web server (e.g., /var/www/html/ or /public_html/)

### 2. Configure Database
### 2. Configure Database
1. Create a ``.env`` file in the root directory:
   ``````bash
   touch .env
   ``````
2. Edit ``.env`` with your database credentials:
   ``````ini
   DB_HOST=localhost
   DB_USER=your_user
   DB_PASS=your_password
   DB_NAME=citcoder_Quality
   ``````

### 3. Set Permissions
``````bash
# Make directories writable
chmod 755 backups/ logs/ cache/ temp/ -R

# Make scripts executable
chmod +x scripts/*.php
``````

### 4. Run Migrations
``````bash
php scripts/migrate.php up
``````

### 5. Verify Installation
``````bash
php scripts/verify.php
``````

### 6. Test Application
Visit your domain and test:
- Admin login
- Form creation
- Form submission

### 7. Configure Backups
Set up automated backups (see DEPLOYMENT.md):
``````bash
crontab -e
# Add: 0 2 * * * php /path/to/scripts/backup_database.php --type=daily
``````

## Troubleshooting

### Database Connection Failed
- Check DB credentials in DbConnection.php
- Ensure MySQL server is running
- Check firewall allows connections

### Permission Denied
``````bash
chown -R www-data:www-data /var/www/html/
``````

### Scripts Not Executable
``````bash
chmod +x scripts/*.php
``````

## Support
For detailed deployment guide, see DEPLOYMENT.md
For automation guide, see DEPLOYMENT_AUTOMATION.md

---
Package created: $timestamp
"@

$installGuidePath = Join-Path $tempDir "INSTALL.md"
$installGuide | Out-File -FilePath $installGuidePath -Encoding UTF8
Write-Success " Installation guide created"

Write-Info "`nStep 6: Compressing package..."
# Remove existing package if present
if (Test-Path $outputPath) {
    Remove-Item $outputPath -Force
}

# Compress
Compress-Archive -Path "$tempDir\*" -DestinationPath $outputPath -CompressionLevel Optimal
Write-Success " Package compressed"

Write-Info "`nStep 7: Cleaning up..."
Remove-Item $tempDir -Recurse -Force
Write-Success " Temporary files removed"

# Final summary
$packageSize = (Get-Item $outputPath).Length
Write-Host ""
Write-Info "================================================================================"
Write-Success "  Package Created Successfully!"
Write-Info "================================================================================"
Write-Host ""
Write-Host "Package Details:"
Write-Host "  File: $outputPath"
Write-Host "  Size: $([math]::Round($packageSize / 1MB, 2)) MB (compressed)"
Write-Host "  Original: $([math]::Round($totalSize / 1MB, 2)) MB"
Write-Host "  Compression: $([math]::Round((1 - ($packageSize / $totalSize)) * 100, 1))%"
Write-Host "  Files: $fileCount"
Write-Host ""
Write-Host "Next Steps:"
Write-Host "  1. Upload $packageName.zip to your server"
Write-Host "  2. Extract: unzip $packageName.zip"
Write-Host "  3. Follow INSTALL.md in the package"
Write-Host ""
Write-Success "Ready for deployment!"
Write-Host ""

