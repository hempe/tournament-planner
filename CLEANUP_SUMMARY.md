# Project Cleanup Summary

**Date:** 2026-02-15

This document summarizes the recent project reorganization and cleanup.

## 📁 File Organization

### New Directory Structure

```
/
├── database/              # Database setup scripts
│   ├── init.sql          # Moved from root
│   └── README.md
├── examples/              # Integration examples
│   ├── iframe-demo.html  # Moved from root
│   └── README.md
├── scripts/               # Utility scripts
│   ├── hash_password.php # Moved from root
│   └── README.md
└── docs/                  # All documentation (uppercase .md files)
    ├── COMPONENTS.md
    ├── DEPLOYMENT.md      # Moved from root
    ├── IFRAME_MODE.md
    ├── INSTALLATION.md    # Moved from root
    ├── ROUTING.md         # Renamed from routing.md
    └── TESTING.md
```

## 🗑️ Files Removed

### Obsolete Documentation
- `MIGRATION.md` - Historical migration guide (migration is complete)
- `docs/components.md` - Duplicate documentation (kept COMPONENTS.md)
- `docs/deployment.md` - Duplicate documentation (moved DEPLOYMENT.md to docs/)

### Temporary/Generated Files
- `TP.session.sql` - SQL session file (added `*.session.sql` to .gitignore)
- `test.php` - Temporary test file
- `cookies.txt` - Temporary file

## 📝 Documentation Updates

### Fixed References
1. **Locale codes updated** in all docs:
   - `de_CH` → `de`
   - `en_US` → `en`
   - `es_ES` → `es`

2. **File paths updated**:
   - `init.sql` → `database/init.sql`
   - `RouterNew.php` → `Router.php` (correct filename)

3. **Consistency improvements**:
   - All docs/*.md files now use UPPERCASE names
   - Removed duplicate files
   - Moved all documentation to docs/ folder

## 🔄 Locale Simplification

Changed from regional locales to simple language codes:
- Renamed `resources/lang/de_CH.php` → `de.php`
- Renamed `resources/lang/en_US.php` → `en.php`
- Renamed `resources/lang/es_ES.php` → `es.php`
- Updated all code references
- Updated all translation keys
- All 19 tests passing ✅

## ✅ Testing Improvements

1. **Removed manual coverage report** - Now using PHPUnit's built-in coverage
2. **Enhanced test runner** - `run-tests.sh` now auto-detects coverage support
3. **Added translation validation** - New `TranslationValidationTest.php` (5 tests)
4. **Proper test documentation** - Updated `tests/README.md` with PHPUnit coverage instructions

## 📦 New README Files

Added documentation for organized folders:
- `scripts/README.md` - Explains utility scripts
- `database/README.md` - Database setup instructions
- `examples/README.md` - Integration examples

## 🎯 Benefits

1. **Clearer Organization**: Helper files separated by purpose
2. **Better Documentation**: All docs in one place with consistent naming
3. **No Duplicates**: Removed redundant documentation
4. **Up-to-Date**: Fixed outdated references and paths
5. **Cleaner Root**: Only essential files in project root

## 📚 Main Documentation Files

| File | Purpose |
|------|---------|
| `README.md` | Project overview and quick start |
| `CLAUDE.md` | Architecture and development guide |
| `docs/INSTALLATION.md` | Complete installation instructions |
| `docs/DEPLOYMENT.md` | Production deployment guide |
| `docs/COMPONENTS.md` | UI component documentation |
| `docs/ROUTING.md` | Routing system documentation |
| `docs/IFRAME_MODE.md` | Iframe embedding guide |
| `docs/TESTING.md` | Testing documentation |
| `tests/README.md` | Test runner and coverage guide |

## 🔍 Verification

All tests passing after cleanup:
```bash
./run-tests.sh
# 19 tests, 344 assertions - all passing ✅
```

Directory structure validated:
```bash
tree -L 2 -I 'vendor|node_modules|coverage*|logs'
```

## 🎉 Result

The project is now:
- ✅ Well-organized with logical folder structure
- ✅ Documentation up-to-date and accurate
- ✅ No duplicate or obsolete files
- ✅ All tests passing
- ✅ Locale codes simplified
- ✅ Ready for continued development
