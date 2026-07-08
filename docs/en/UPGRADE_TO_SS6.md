# Upgrade Guide to Silverstripe 6

This document outlines the necessary changes to upgrade your project to be compatible with this module's Silverstripe 6 version.

## Dependency Updates

### ⚠️ BREAKING CHANGE: Core Dependency Removed

The dependency on `sunnysideup/ecommerce-google-shopping-feed` has been removed from the module's `composer.json`.

🚨 **CRITICAL REVIEW REQUIRED / RISKY: Your project must now directly require `sunnysideup/ecommerce-google-shopping-feed` if it is still needed. The upgrade notes indicate there may not be a compatible stable release available yet.**

You will need to manually add it to your project's `composer.json`:

```json
{
    "require": {
        "sunnysideup/ecommerce-google-shopping-feed": "5.x-dev"
    }
}
```
