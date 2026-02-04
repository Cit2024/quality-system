# Production Package Tool - Usage Guide

## Overview

The `package.ps1` script creates a production-ready compressed package of the Quality System, excluding development files, tests, and documentation.

## Quick Start

```powershell
# Create production package
.\scripts\package.ps1

# Package will be created in: ../releases/quality-system-YYYY-MM-DD_HH-mm-ss.zip
```

## Options

```powershell
# Custom output directory
.\scripts\package.ps1 -OutputDir "C:\Deploy"

# Include tests in package
.\scripts\package.ps1 -IncludeTests
```

## What Gets Excluded

### Always Excluded:
- **Version Control**: `.git`, `.gitignore`, `.gitattributes`
- **IDE Files**: `.vscode`, `.idea`, `*.code-workspace`
- **Tests**: `tests/`, `phpunit.xml` (unless -IncludeTests)
- **Documentation**: `README.md`, `ARCHITECTURE.md`
- **Development**: `node_modules`, `.env.example`
- **Build Artifacts**: `*.log`, `logs/*`, `cache/*`, `temp/*`
- **Backups**: `backups/*` (created fresh on server)
- **OS Files**: `.DS_Store`, `Thumbs.db`, `desktop.ini`

### Always Included:
- All PHP application files
- Configuration templates
- Database migrations
- Deployment scripts (deploy.php, migrate.php, verify.php)
- Essential documentation (DEPLOYMENT.md, DEPLOYMENT_AUTOMATION.md)
- Helper files
- Frontend assets (CSS, JS, images)

## Package Contents

The generated package includes:

1. **Application Files** (~173 files)
   - PHP scripts and helpers
   - Configuration files
   - Frontend assets

2. **Deployment Tools**
   - `scripts/deploy.php`
   - `scripts/migrate.php`
   - `scripts/verify.php`
   - `scripts/backup_database.php`
   - `scripts/restore_database.php`

3. **Database**
   - All migration files
   - Schema definitions

4. **Documentation**
   - `DEPLOYMENT.md`
   - `DEPLOYMENT_AUTOMATION.md`
   - **`INSTALL.md`** (automatically generated)
   - **`MANIFEST.json`** (package metadata)

5. **Required Directories** (created empty)
   - `backups/daily/`
   - `backups/weekly/`
   - `backups/monthly/`
   - `backups/logs/`
   - `logs/`
   - `cache/`
   - `temp/`
   - Each with `.htaccess` protection

## Generated Files

### INSTALL.md
Quick installation guide included in every package with:
- Package information (date, size, file count)
- Step-by-step installation steps
- Troubleshooting tips
- Support information

### MANIFEST.json
Package metadata including:
```json
{
  "package_name": "quality-system-2026-02-04_12-27-07",
  "created_at": "2026-02-04_12-27-07",
  "php_version_required": "8.0+",
  "mysql_version_required": "5.7+",
  "file_count": 173,
  "total_size_mb": 2.81,
  "includes_tests": false,
  "deployment_steps": [...]
}
```

## Typical Output

```
================================================================================
  Quality System - Production Package Builder
================================================================================

Configuration:
  Project Root: C:\Users\iG\Downloads\quality-system
  Package Name: quality-system-2026-02-04_12-27-07
  Output: ..\releases\quality-system-2026-02-04_12-27-07.zip
  Include Tests: False

Step 1: Creating temporary directory...
✓ Temporary directory created

Step 2: Copying production files...
✓ Copied 173 files (2.81 MB)

Step 3: Creating required directories...
✓ Required directories created

Step 4: Creating deployment manifest...
✓ Manifest created

Step 5: Creating installation guide...
✓ Installation guide created

Step 6: Compressing package...
✓ Package compressed

Step 7: Cleaning up...
✓ Temporary files removed

================================================================================
  Package Created Successfully!
================================================================================

Package Details:
  File: ..\releases\quality-system-2026-02-04_12-27-07.zip
  Size: 1.22 MB (compressed)
  Original: 2.81 MB
  Compression: 56.5%
  Files: 173

Next Steps:
  1. Upload quality-system-2026-02-04_12-27-07.zip to your server
  2. Extract: unzip quality-system-2026-02-04_12-27-07.zip
  3. Follow INSTALL.md in the package

Ready for deployment!
```

## Deployment to Server

### 1. Upload Package

```bash
# Via SCP
scp quality-system-*.zip user@server:/var/www/

# Via FTP
# Use your FTP client to upload the .zip file
```

### 2. Extract on Server

```bash
# SSH into server
ssh user@server

# Navigate to web directory
cd /var/www/html/

# Extract package
unzip quality-system-2026-02-04_12-27-07.zip

# Set permissions
chmod 755 backups/ logs/ cache/ temp/ -R
chmod +x scripts/*.php
```

### 3. Configure & Deploy

```bash
# 1. Edit database credentials
nano config/DbConnection.php

# 2. Run migrations
php scripts/migrate.php up

# 3. Verify installation
php scripts/verify.php

# 4. Test the site
# Visit your domain
```

## Compression Statistics

Typical compression results:
- **Original Size**: ~2.8 MB
- **Compressed Size**: ~1.2 MB
- **Compression Ratio**: ~56%
- **File Count**: ~173 files (without tests)
- **With Tests**: ~190 files, ~3.0 MB original

## Customization

### Modify Exclusions

Edit `.packageignore` to add/remove exclusion patterns:

```
# Add custom exclusions
*.draft
scratch/
```

### Modify Script

Edit `scripts/package.ps1` to customize:
- Additional required directories
- Package manifest content
- Installation guide text
- Compression level

## Troubleshooting

### "Execution Policy" Error

```powershell
# Set execution policy for current session
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass

# Then run script
.\scripts\package.ps1
```

### Package Too Large

```powershell
# Check what's being included
Get-ChildItem -Recurse | Where-Object {$_.PSIsContainer -eq $false} | 
  Sort-Object Length -Descending | Select-Object -First 20
```

### Missing Files in Package

- Check `.packageignore` for over-aggressive exclusions
- Verify files exist in source directory
- Check the `$excludePatterns` array in `package.ps1`

## Best Practices

1. **Create packages frequently** - Keep versioned releases
2. **Test on staging first** - Always test extracted package
3. **Keep old packages** - For rollback capability
4. **Document changes** - Update CHANGELOG.md before packaging
5. **Verify package** - Extract and test locally before deploying

## Related Documentation

- [DEPLOYMENT.md](../DEPLOYMENT.md) - Full deployment guide
- [DEPLOYMENT_AUTOMATION.md](../DEPLOYMENT_AUTOMATION.md) - Automation tools
- `.packageignore` - Exclusion patterns

---

**Created**: 2026-02-04  
**Version**: 1.0  
**Maintainer**: Mohamed Fouad Bala
