<#
.SYNOPSIS
    Fidele: bridge between the POS in the browser and a Sberbank card terminal.

.DESCRIPTION
    Listens on http://localhost:<Port>/ and drives the terminal through
    Sberbank "Pilot" (sb_pilot.exe), which is installed together with the
    terminal driver (usually C:\sc552).

        GET  /status          is the agent running, is Pilot found
        POST /pay {amount}    charge <amount> roubles, wait for the result

    Only requests from -AllowedOrigin carrying the X-Terminal-Token header
    are accepted, so other sites opened on this computer cannot start a
    payment. Payments run one at a time.

    Pilot writes the result to the "e" file (code and message, 0 = approved)
    and the slip to the "p" file in its directory.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File fidele-terminal-agent.ps1 `
        -Token "<token from admin settings>" `
        -AllowedOrigin "https://fidele.example.ru"
#>
param(
    [Parameter(Mandatory = $true)][string]$Token,
    [Parameter(Mandatory = $true)][string]$AllowedOrigin,
    [string]$PilotDir = 'C:\sc552',
    [int]$Port = 8765,
    # Pilot operation code for a purchase.
    [int]$PurchaseOperation = 1,
    # Code page Pilot writes its result files in (866 = DOS Cyrillic).
    [int]$FileCodePage = 866
)

$ErrorActionPreference = 'Stop'
$AllowedOrigin = $AllowedOrigin.TrimEnd('/')
$pilotExe = Join-Path $PilotDir 'sb_pilot.exe'
$resultFile = Join-Path $PilotDir 'e'
$slipFile = Join-Path $PilotDir 'p'
$fileEncoding = [System.Text.Encoding]::GetEncoding($FileCodePage)

function Write-Log([string]$message) {
    Write-Host ("[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $message)
}

function Send-Json($context, [int]$status, $body) {
    $response = $context.Response
    $response.StatusCode = $status
    $response.ContentType = 'application/json; charset=utf-8'
    $bytes = [System.Text.Encoding]::UTF8.GetBytes(($body | ConvertTo-Json -Compress -Depth 4))
    $response.ContentLength64 = $bytes.Length
    $response.OutputStream.Write($bytes, 0, $bytes.Length)
    $response.OutputStream.Close()
}

function Read-PilotResult {
    if (-not (Test-Path $resultFile)) {
        return @{ code = -1; message = 'Пилот не записал результат операции' }
    }

    $lines = $fileEncoding.GetString([System.IO.File]::ReadAllBytes($resultFile)) -split "`r?`n" | Where-Object { $_ -ne '' }
    $first = [string]$lines[0]

    # Either "0,Одобрено" on one line, or the code and the message on two lines.
    if ($first.Contains(',')) {
        $code, $message = $first.Split(',', 2)
    } else {
        $code = $first
        $message = if ($lines.Count -gt 1) { [string]$lines[1] } else { '' }
    }

    $parsed = 0
    if (-not [int]::TryParse($code.Trim(), [ref]$parsed)) {
        $parsed = -1
    }

    return @{ code = $parsed; message = $message.Trim() }
}

function Invoke-Purchase([decimal]$amount) {
    $kopecks = [long][math]::Round($amount * 100)

    foreach ($file in @($resultFile, $slipFile)) {
        if (Test-Path $file) { Remove-Item $file -Force }
    }

    Write-Log "Оплата $amount руб. ($kopecks коп.)"
    $process = Start-Process -FilePath $pilotExe -ArgumentList "$PurchaseOperation $kopecks" -WorkingDirectory $PilotDir -Wait -PassThru

    $result = Read-PilotResult
    $slip = ''
    if (Test-Path $slipFile) {
        $slip = $fileEncoding.GetString([System.IO.File]::ReadAllBytes($slipFile))
    }

    $approved = ($result.code -eq 0)
    Write-Log ("Результат: код {0}, {1} (exit {2})" -f $result.code, $result.message, $process.ExitCode)

    return @{
        approved = $approved
        code = $result.code
        message = if ($approved -and $result.message -eq '') { 'Одобрено' } else { $result.message }
        amount = $amount
        slip = $slip
    }
}

if (-not (Test-Path $pilotExe)) {
    Write-Log "ВНИМАНИЕ: не найден $pilotExe. Укажите папку Пилота параметром -PilotDir."
}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://localhost:$Port/")
$listener.Start()
Write-Log "Программа связи с терминалом запущена: http://localhost:$Port/ (сайт: $AllowedOrigin)"

try {
    while ($listener.IsListening) {
        $context = $listener.GetContext()
        $request = $context.Request
        $origin = [string]$request.Headers['Origin']

        if ($origin -ne '' -and $origin.TrimEnd('/') -ne $AllowedOrigin) {
            Send-Json $context 403 @{ error = 'Запрос не с сайта кассы' }
            continue
        }

        if ($origin -ne '') {
            $context.Response.Headers['Access-Control-Allow-Origin'] = $origin
            $context.Response.Headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS'
            $context.Response.Headers['Access-Control-Allow-Headers'] = 'Content-Type, X-Terminal-Token'
            # Chrome asks a public site for permission to reach localhost.
            $context.Response.Headers['Access-Control-Allow-Private-Network'] = 'true'
            $context.Response.Headers['Vary'] = 'Origin'
        }

        if ($request.HttpMethod -eq 'OPTIONS') {
            $context.Response.StatusCode = 204
            $context.Response.Close()
            continue
        }

        if ($request.Headers['X-Terminal-Token'] -ne $Token) {
            Send-Json $context 401 @{ error = 'Неверный токен' }
            continue
        }

        try {
            switch ("$($request.HttpMethod) $($request.Url.AbsolutePath)") {
                'GET /status' {
                    Send-Json $context 200 @{ ok = $true; pilot = (Test-Path $pilotExe) }
                }
                'POST /pay' {
                    $reader = New-Object System.IO.StreamReader($request.InputStream, [System.Text.Encoding]::UTF8)
                    $payload = $reader.ReadToEnd() | ConvertFrom-Json
                    $amount = [decimal]$payload.amount

                    if ($amount -le 0 -or $amount -gt 1000000) {
                        Send-Json $context 422 @{ error = 'Неверная сумма' }
                    } elseif (-not (Test-Path $pilotExe)) {
                        Send-Json $context 500 @{ error = "Не найден $pilotExe" }
                    } else {
                        Send-Json $context 200 (Invoke-Purchase $amount)
                    }
                }
                default {
                    Send-Json $context 404 @{ error = 'Не найдено' }
                }
            }
        } catch {
            Write-Log "Ошибка: $($_.Exception.Message)"
            Send-Json $context 500 @{ error = $_.Exception.Message }
        }
    }
} finally {
    $listener.Stop()
}
