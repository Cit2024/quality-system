#!/usr/bin/env python3
"""
Quality System - Professional Packaging Tool
Creates a production-ready ZIP archive of the project.
"""

import os
import sys
import zipfile
import shutil
import datetime
import re

# Configuration
PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUTPUT_DIR = os.path.join(PROJECT_ROOT, 'releases')
IGNORE_FILE = os.path.join(PROJECT_ROOT, '.packageignore')

# Critical directories to verify
CRITICAL_DIRS = [
    'config',
    'helpers',
    'forms',
    'statistics',
    'statistics/analytics',
    'statistics/analytics/config',
    'statistics/analytics/shared',
    'statistics/analytics/targets',
    'statistics/analytics/targets/views',
    'statistics/analytics/templates',
    'scripts'
]

def info(msg):
    print(f"\033[36m[INFO] {msg}\033[0m")

def success(msg):
    print(f"\033[32m[OK] {msg}\033[0m")

def error(msg):
    print(f"\033[31m[ERROR] {msg}\033[0m")

def load_exclusions():
    # Only hardcode ABSOLUTELY ESSENTIAL exclusions that would break the package or are dangerous
    excludes = [
        # Version Control
        r'^\.git', 
        
        # The release folder itself (prevent recursion)
        r'^releases', 
        
        # The packaging script itself
        r'^scripts/package.py', 
        r'^scripts/package.ps1', # Legacy
        r'^scripts/package.php', # Legacy
    ]
    
    if os.path.exists(IGNORE_FILE):
        info(f"Reading exclusions from {IGNORE_FILE}...")
        with open(IGNORE_FILE, 'r') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#'):
                    # Handle negation (re-inclusion)
                    if line.startswith('!'):
                        # Python re doesn't support direct negation easily in this list context,
                        # but we can try to handle it. For now, simpliest way is to NOT add it to excludes.
                        # If a user wants to re-include something, they usually remove it from ignore.
                        # But standard .gitignore syntax allows !. 
                        # For this simple script, we'll just ignore lines starting with ! (so they don't become exclusions)
                        # To truly support re-inclusion, we'd need more complex logic.
                        continue
                    
                    # Convert glob-like patterns to basic regex
                    # Escape dots
                    pattern = line.replace('.', r'\.')
                    # Star to .*
                    pattern = pattern.replace('*', '.*')
                    
                    # Handle directory traversal
                    if line.endswith('/'):
                        pattern += '.*'
                    
                    # Anchoring (optional, but good for exact matches)
                    # If it doesn't start with *, assume it matches from root or any directory?
                    # Gitignore is complex. We'll simplify: 
                    # If it has no slash, match anywhere (add .* prefix? no, re.search does that)
                    # We usually want to match the START of the relative path if it's a specific file
                    
                    excludes.append(pattern)
                    
    return excludes

def is_excluded(path, exclusions):
    # Normalize path to forward slashes
    path = path.replace('\\', '/')
    for pattern in exclusions:
        if re.search(pattern, path):
            return True
    return False

def main():
    print("\n============================================")
    print("  Quality System - Packaging Tool (Python)")
    print("============================================")

    # Ensure output directory exists
    if not os.path.exists(OUTPUT_DIR):
        os.makedirs(OUTPUT_DIR)

    timestamp = datetime.datetime.now().strftime('%Y-%m-%d_%H-%M-%S')
    zip_filename = f"quality-system-{timestamp}.zip"
    zip_path = os.path.join(OUTPUT_DIR, zip_filename)

    info(f"Project Root: {PROJECT_ROOT}")
    info(f"Output File: {zip_path}")

    # 1. Verification
    info("\nStep 1: Pre-packaging Verification...")
    missing = []
    for d in CRITICAL_DIRS:
        full_path = os.path.join(PROJECT_ROOT, d)
        if not os.path.exists(full_path):
            missing.append(d)
            error(f"Missing directory: {d}")
        else:
            # Count files
            file_count = 0
            for root, dirs, files in os.walk(full_path):
                file_count += len(files)
            success(f"Found {d} ({file_count} files)")

    if missing:
        error("Critical directories missing. Packaging aborted.")
        sys.exit(1)

    # 2. Create ZIP
    info("\nStep 2: Creating Archive...")
    exclusions = load_exclusions()
    
    file_count = 0
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(PROJECT_ROOT):
            for file in files:
                file_path = os.path.join(root, file)
                rel_path = os.path.relpath(file_path, PROJECT_ROOT)
                
                if is_excluded(rel_path, exclusions):
                    continue
                    
                zipf.write(file_path, rel_path)
                file_count += 1
        
        # Add empty required directories
        for d in ['logs', 'temp', 'cache', 'backups']:
            zip_info = zipfile.ZipInfo(d + '/')
            zipf.writestr(zip_info, '')
            zipf.writestr(f"{d}/.htaccess", "Deny from all")

    success(f"Archive created with {file_count} files.")

    # 3. Verify ZIP
    info("\nStep 3: Verifying Archive Content...")
    verified = True
    with zipfile.ZipFile(zip_path, 'r') as zipf:
        namelist = zipf.namelist()
        for d in CRITICAL_DIRS:
            # Normalize for zip check
            check_path = d.replace('\\', '/')
            found = any(name.startswith(check_path) for name in namelist)
            
            if found:
                success(f"Verified in ZIP: {d}")
            else:
                error(f"MISSING in ZIP: {d}")
                verified = False

    print("\n============================================")
    if verified:
        success("Packaging Complete Successfully!")
        print(f"File: {zip_path}")
        size_mb = os.path.getsize(zip_path) / (1024 * 1024)
        print(f"Size: {size_mb:.2f} MB")
        print("\nTo deploy:")
        print(f"  scp {zip_filename} citcoder@erp.cit.edu.ly:...")
    else:
        error("Packaging FAILED verification.")
        sys.exit(1)
    print("============================================")

if __name__ == '__main__':
    main()
