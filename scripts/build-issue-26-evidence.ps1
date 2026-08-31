$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$rawRoot = Join-Path $repoRoot 'tmp\issue-26\raw'
$localRoot = Join-Path $repoRoot 'tmp\issue-26\local-raw'
$outputRoot = Join-Path $repoRoot 'evidence\issue-26'
$workRoot = Join-Path $repoRoot 'tmp\issue-26\evidence-components'

New-Item -ItemType Directory -Force -Path $outputRoot, $workRoot | Out-Null

function Invoke-Magick {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]] $Arguments)

    & magick @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "ImageMagick failed with exit code $LASTEXITCODE"
    }
}

function New-EvidenceFrame {
    param(
        [string] $Source,
        [string] $Crop,
        [string] $Canvas,
        [string] $Label,
        [string] $Output
    )

    if (-not (Test-Path -LiteralPath $Source)) {
        throw "Missing raw evidence: $Source"
    }

    Invoke-Magick $Source '-crop' $Crop '+repage' '-resize' $Canvas '-gravity' 'center' `
        '-background' '#efede7' '-extent' $Canvas '-gravity' 'northwest' '-fill' 'white' `
        '-undercolor' '#0b0b0ccc' '-pointsize' '20' '-annotate' '+14+14' $Label `
        '-strip' '-quality' '84' $Output
}

function New-Montage {
    param(
        [string[]] $Sources,
        [string] $Tile,
        [string] $Output
    )

    Invoke-Magick 'montage' @Sources '-tile' $Tile '-geometry' '+10+10' '-background' '#dedbd4' `
        '-strip' '-quality' '84' $Output
}

function New-FullResolutionEvidence {
    param(
        [string] $Source,
        [string] $Output
    )

    if (-not (Test-Path -LiteralPath $Source)) {
        throw "Missing full-resolution evidence: $Source"
    }

    Invoke-Magick $Source '-strip' '-sampling-factor' '4:2:0' '-quality' '82' $Output
}

# Desktop first-fold matrix at every required desktop viewport.
$desktopFrames = @()
foreach ($viewport in @(
    @{ Name = 'desktop-1920x1080'; Crop = '1920x1080+0+0'; Label = '1920 x 1080' },
    @{ Name = 'desktop-1440x900'; Crop = '1440x900+0+0'; Label = '1440 x 900' },
    @{ Name = 'desktop-1366x768'; Crop = '1366x768+0+0'; Label = '1366 x 768' },
    @{ Name = 'desktop-1280x800'; Crop = '1280x800+0+0'; Label = '1280 x 800' }
)) {
    $frame = Join-Path $workRoot ("home-{0}.jpg" -f $viewport.Name)
    New-EvidenceFrame (Join-Path $rawRoot "$($viewport.Name)\home.png") $viewport.Crop '720x405' $viewport.Label $frame
    $desktopFrames += $frame
}
New-Montage $desktopFrames '2x2' (Join-Path $outputRoot '01-home-desktop-matrix.jpg')

# Mobile first folds, retaining the real viewport aspect ratios.
$mobile390 = Join-Path $workRoot 'home-mobile-390.jpg'
$mobile320 = Join-Path $workRoot 'home-mobile-320.jpg'
New-EvidenceFrame (Join-Path $rawRoot 'mobile-touch-390x844\home.png') '1170x2532+0+0' '390x844' '390 x 844 / touch' $mobile390
New-EvidenceFrame (Join-Path $rawRoot 'mobile-narrow-320x800\home.png') '320x800+0+0' '390x844' '320 x 800 / narrow' $mobile320
New-Montage @($mobile390, $mobile320) '2x1' (Join-Path $outputRoot '02-home-mobile-matrix.jpg')

# Mobile catalogue interaction and content-dependency states.
$catalogFrames = @()
foreach ($state in @(
    @{ File = 'catalog-filter-open.png'; Label = 'Filter drawer open' },
    @{ File = 'catalog-loading.png'; Label = 'Live request loading' },
    @{ File = 'catalog-empty-results.png'; Label = 'Empty results' },
    @{ File = 'product-detail.png'; Label = 'NDI test item / fallback' }
)) {
    $frame = Join-Path $workRoot ("catalog-{0}.jpg" -f [IO.Path]::GetFileNameWithoutExtension($state.File))
    New-EvidenceFrame (Join-Path $rawRoot "mobile-touch-390x844\$($state.File)") '1170x2532+0+0' '390x844' $state.Label $frame
    $catalogFrames += $frame
}
New-Montage $catalogFrames '2x2' (Join-Path $outputRoot '03-catalog-states.jpg')

# The same untranslated framework validation keys in Bokmaal and hidden Russian parity mode.
$validationFrames = @()
foreach ($validation in @(
    @{ File = 'service-request-validation-error.png'; Label = 'Bokmaal validation' },
    @{ File = 'service-request-validation-error-ru.png'; Label = 'Russian parity validation' }
)) {
    $frame = Join-Path $workRoot ("validation-{0}.jpg" -f [IO.Path]::GetFileNameWithoutExtension($validation.File))
    New-EvidenceFrame (Join-Path $localRoot "local-desktop-1440x900\$($validation.File)") '1020x1600+150+600' '600x940' $validation.Label $frame
    $validationFrames += $frame
}
New-Montage $validationFrames '2x1' (Join-Path $outputRoot '04-service-validation-locales.jpg')

# Desktop service detail: long article constrained to the first half, short CTA isolated in the second half.
Invoke-Magick (Join-Path $rawRoot 'desktop-1440x900\service-mobile.png') '-crop' '1440x3400+0+600' '+repage' `
    '-resize' '960x' '-gravity' 'northwest' '-fill' 'white' '-undercolor' '#0b0b0ccc' `
    '-pointsize' '24' '-annotate' '+18+18' 'Service detail / 1440 x 900' '-strip' '-quality' '84' `
    (Join-Path $outputRoot '05-service-detail-layout.jpg')

# Representative public surfaces in the approved sharp direction.
$surfaceFrames = @()
foreach ($surface in @(
    @{ File = 'about.png'; Label = 'About' },
    @{ File = 'contact.png'; Label = 'Contact and map' },
    @{ File = 'prices.png'; Label = 'Prices' },
    @{ File = 'store.png'; Label = 'Store landing' }
)) {
    $frame = Join-Path $workRoot ("surface-{0}.jpg" -f [IO.Path]::GetFileNameWithoutExtension($surface.File))
    New-EvidenceFrame (Join-Path $rawRoot "desktop-1440x900\$($surface.File)") '1440x900+0+0' '720x450' $surface.Label $frame
    $surfaceFrames += $frame
}
New-Montage $surfaceFrames '2x2' (Join-Path $outputRoot '06-key-public-surfaces.jpg')

# Safe local synthetic commerce states; no NDI, payment, email or production side effects.
$commerceFrames = @()
foreach ($state in @(
    @{ File = 'catalog-populated.png'; Label = 'Synthetic populated catalog' },
    @{ File = 'product-with-media.png'; Label = 'Synthetic product with media' },
    @{ File = 'cart-populated.png'; Label = 'Populated cart' },
    @{ File = 'checkout-validation-error.png'; Label = 'Checkout validation' }
)) {
    $frame = Join-Path $workRoot ("commerce-{0}.jpg" -f [IO.Path]::GetFileNameWithoutExtension($state.File))
    New-EvidenceFrame (Join-Path $localRoot "local-mobile-touch-390x844\$($state.File)") '1170x2532+0+0' '390x844' $state.Label $frame
    $commerceFrames += $frame
}
New-Montage $commerceFrames '2x2' (Join-Path $outputRoot '07-local-commerce-states.jpg')

# Representative default/hover/focus/selected component states from the primary desktop viewport.
$stateFrames = @()
foreach ($state in @(
    @{ File = 'state-header-booking-default.png'; Label = 'Primary / default' },
    @{ File = 'state-header-booking-hover.png'; Label = 'Primary / hover' },
    @{ File = 'state-header-booking-focus.png'; Label = 'Primary / keyboard focus' },
    @{ File = 'state-finder-tab-default.png'; Label = 'Finder tab / default' },
    @{ File = 'state-finder-tab-selected.png'; Label = 'Finder tab / selected' },
    @{ File = 'state-finder-tab-focus.png'; Label = 'Finder tab / keyboard focus' }
)) {
    $source = Join-Path $rawRoot "desktop-1440x900\$($state.File)"
    $frame = Join-Path $workRoot ("state-{0}.jpg" -f [IO.Path]::GetFileNameWithoutExtension($state.File))
    if (-not (Test-Path -LiteralPath $source)) {
        throw "Missing raw interaction evidence: $source"
    }
    Invoke-Magick $source '-resize' '360x120' '-gravity' 'center' '-background' '#dedbd4' '-extent' '360x120' `
        '-gravity' 'northwest' '-fill' 'white' '-undercolor' '#0b0b0ccc' '-pointsize' '16' `
        '-annotate' '+8+8' $state.Label '-strip' '-quality' '84' $frame
    $stateFrames += $frame
}
New-Montage $stateFrames '3x2' (Join-Path $outputRoot '08-reusable-interaction-states.jpg')

# Selected screenshots stay at their native pixel dimensions. These are the
# reviewable proofs for every P1/P2 and for the MATCH results disputed in the
# first audit; the compact sheets above remain navigation aids only.
$fullResolutionEvidence = @(
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\sticky-header-after-light.png'; Output = '09-sticky-header-light-1440x900.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\sticky-header-after-dark.png'; Output = '10-sticky-header-dark-1440x900.jpg' },
    @{ Source = Join-Path $rawRoot 'mobile-touch-390x844\sticky-header-after-light.png'; Output = '11-sticky-header-light-mobile-390x844.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\services.png'; Output = '12-services-index-1440-full.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\service-mobile.png'; Output = '13-service-detail-1440-full.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\tyres.png'; Output = '14-catalog-1440-full.jpg' },
    @{ Source = Join-Path $rawRoot 'mobile-touch-390x844\catalog-filter-open.png'; Output = '15-catalog-filter-open-mobile-full.jpg' },
    @{ Source = Join-Path $rawRoot 'mobile-touch-390x844\catalog-loading.png'; Output = '16-catalog-loading-mobile-full.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\product-test.png'; Output = '17-product-detail-1440-full.jpg' },
    @{ Source = Join-Path $rawRoot 'mobile-narrow-320x800\product-test.png'; Output = '18-product-detail-320-full.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\cart-empty.png'; Output = '19-cart-empty-1440-full.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\request-generic.png'; Output = '20-request-form-1440-full.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\critical-button-outline-hover.png'; Output = '21-button-outline-hover-1440x900.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\critical-button-link-hover.png'; Output = '22-button-link-hover-1440x900.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\critical-desktop-submenu-child-focus.png'; Output = '22-submenu-keyboard-focus-1440x900.jpg' },
    @{ Source = Join-Path $rawRoot 'mobile-touch-390x844\critical-mobile-menu-group-open.png'; Output = '23-mobile-menu-group-focus-390x844.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\critical-contact-map-after-8s.png'; Output = '24-contact-map-loaded-1440x900.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\home.png'; Output = '25-home-1440-full.jpg' },
    @{ Source = Join-Path $rawRoot 'desktop-1440x900\about.png'; Output = '26-about-1440-full.jpg' },
    @{ Source = Join-Path $rawRoot 'mobile-touch-390x844\tyres.png'; Output = '27-catalog-mobile-390-full.jpg' },
    @{ Source = Join-Path $localRoot 'local-desktop-1440x900\service-request-validation-error.png'; Output = '28-validation-bokmaal-1440-full.jpg' },
    @{ Source = Join-Path $localRoot 'local-desktop-1440x900\service-request-validation-error-ru.png'; Output = '29-validation-russian-1440-full.jpg' },
    @{ Source = Join-Path $localRoot 'local-mobile-touch-390x844\cart-populated.png'; Output = '30-cart-populated-mobile-full.jpg' },
    @{ Source = Join-Path $localRoot 'local-mobile-touch-390x844\checkout-validation-error.png'; Output = '31-checkout-validation-mobile-full.jpg' }
)

foreach ($evidence in $fullResolutionEvidence) {
    New-FullResolutionEvidence $evidence.Source (Join-Path $outputRoot $evidence.Output)
}

Get-ChildItem -LiteralPath $outputRoot -File | Sort-Object Name | Select-Object Name, Length
