# Quality System - Professional Deployment Guide

## 1. Create Deployment Package
Run the Python packaging tool to create a production-ready ZIP file.
```powershell
python scripts/package.py
```
*This creates a zip file in the `releases/` directory.*

## 2. Upload to Server
Use SCP to upload the package to your server.
```powershell
scp releases/quality-system-YYYY-MM-DD_HH-MM-SS.zip citcoder@erp.cit.edu.ly:/home/citcoder/erp/quality-system/
```

## 3. Connect to Server
SSH into your server to perform the installation.
```powershell
ssh citcoder@erp.cit.edu.ly
```

## 4. Install on Server
Run the following commands on the server:
```bash
cd /home/citcoder/erp/quality-system

# Unzip the package (replace with actual filename)
unzip -o quality-system-YYYY-MM-DD_HH-MM-SS.zip

# Fix permissions
chmod +x scripts/fix_permissions.sh
./scripts/fix_permissions.sh
```

## 5. Verify Deployment
Run the debug router to ensure everything is working correctly.
```bash
php statistics/debug_router.php
```

## Troubleshooting
- **Arabic Text in PDFs**: If reversed, clear browser cache. The fix is in `PDFReportGenerator.js`.
- **Missing Files**: Check `.packageignore` locally.
