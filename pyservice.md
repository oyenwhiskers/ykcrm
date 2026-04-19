# Commands to manage the uvicorn server running the extraction service
Get-NetTCPConnection -LocalPort 8001 -State Listen

# Find the process ID (PID) of the uvicorn server running on port 8001
Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*uvicorn app.main:app*8001*' } | Select-Object ProcessId,CommandLine

# Stop the uvicorn server using the PID obtained from the previous command
Stop-Process -Id <pid> -Force

# To run the uvicorn server with 2 workers
-m uvicorn app.main:app --host 127.0.0.1 --port 8001 --workers 2