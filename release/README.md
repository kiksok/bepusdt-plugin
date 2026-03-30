# Release Assets

This directory stores the XBoard-ready release package for this plugin.

## Files

- `bepusdt-plugin-xboard.zip`
  - Direct upload package for the XBoard plugin manager
  - Contains a top-level `Bepusdt/` directory
- `SHA256SUMS.txt`
  - SHA-256 checksum for the zip package

## Rebuild

Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\package.ps1
```
