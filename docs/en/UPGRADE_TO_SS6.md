# Upgrade to Silverstripe 6

This document outlines the key changes required to upgrade to version 6 of the `sunnysideup/ecommerce-also-bought` module.

## 🚨 CRITICAL REVIEW REQUIRED / RISKY

**The upgrade of the `FindAlsoBought` BuildTask to a `Command` appears incomplete. A manual review and potential refactoring is required to ensure it functions correctly as a command-line task.**

## ⚠️ BREAKING CHANGES

### New Requirements

- The dependency `sunnysideup/ecommerce` has been upgraded to `^33.0`.
- The dependency `silverstripe/recipe-cms` has been upgraded to `^6.0`.

### Configuration

- The deprecated `SilverStripe\ORM\DatabaseAdmin` configuration for class remapping has been removed. You must now use `SilverStripe\Dev\DbBuild` for these mappings.

### API Changes

- The method `requireDefaultRecords()` in `EcommerceAlsoBoughtDOD` has been renamed to `onRequireDefaultRecords()`.
- The `FindAlsoBought` class, previously a `BuildTask`, has been significantly refactored to work as a `Command`.
    - `run()` method now calls `runOnDemand()`.
    - `run_on_demand()` has been renamed to `runOnDemand()` and its implementation changed.
    - `run()` has been replaced by `execute()`.
    - `DB::alteration_message()` calls have been replaced with `$this->output->writeln()`.
    - `$segment` has been replaced by `$commandName`.

## Other Changes

- The `#[Override]` attribute has been added to the `title()` method in `AlsoBoughtProducts.php` and `ProductsWithAlsoBoughtProducts.php`.
