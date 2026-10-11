
$ErrorActionPreference = "Stop"

Write-Host "================================" -ForegroundColor Cyan
Write-Host "       CareerLy Setup" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan

# 1. Clear Laravel caches
Write-Host "`n[1/5] Clearing caches..." -ForegroundColor Yellow
php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw "Failed to clear caches." }

# 2. Run pending database migrations
Write-Host "`n[2/5] Running migrations..." -ForegroundColor Yellow
php artisan migrate
if ($LASTEXITCODE -ne 0) { throw "Migrations failed." }

# 3. Cache configuration
Write-Host "`n[3/5] Caching configuration..." -ForegroundColor Yellow
php artisan config:cache
if ($LASTEXITCODE -ne 0) { throw "Configuration caching failed." }

# 4. Cache routes
Write-Host "`n[4/5] Caching routes..." -ForegroundColor Yellow
php artisan route:cache
if ($LASTEXITCODE -ne 0) { throw "Route caching failed." }

# 5. Cache views
Write-Host "`n[5/5] Caching views..." -ForegroundColor Yellow
php artisan view:cache
if ($LASTEXITCODE -ne 0) { throw "View caching failed." }

Write-Host "`nCareerLy setup completed successfully!" -ForegroundColor Green



