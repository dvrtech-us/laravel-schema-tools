# Package Release Guide

This guide explains how to automatically create and publish packages when merging to a tag using GitHub Actions.

## 🚀 Automated Release Workflow

The repository now includes a GitHub Actions workflow (`.github/workflows/release.yml`) that automatically:

1. **Triggers on tag creation** (tags starting with `v*`)
2. **Runs quality checks** (tests and static analysis)
3. **Creates a GitHub release** with auto-generated changelog
4. **Publishes to Packagist** (if credentials are configured)

## 📋 Setup Requirements

### 1. Repository Configuration

Ensure your repository is properly configured on Packagist:

1. Go to [Packagist.org](https://packagist.org)
2. Submit your package: `https://github.com/dvrtech-us/laravel-schema-tools`
3. Enable auto-updating via GitHub webhook (recommended)

### 2. GitHub Secrets

Configure the following repository secrets for automatic Packagist updates:

Go to: **Repository → Settings → Secrets and variables → Actions**

Add these secrets:

| Secret Name | Description | Required |
|-------------|-------------|----------|
| `PACKAGIST_USERNAME` | Your Packagist.org username | Optional* |
| `PACKAGIST_TOKEN` | Your Packagist API token | Optional* |

*Optional: If not configured, packages must be manually updated on Packagist.

#### Getting Packagist API Token:

1. Go to [Packagist.org](https://packagist.org) and log in
2. Navigate to your [profile](https://packagist.org/profile/)
3. Click "Show API Token" or "Generate API Token"
4. Copy the token and add it as `PACKAGIST_TOKEN` secret

## 🏷️ Creating Releases

### Method 1: Command Line (Recommended)

```bash
# 1. Ensure you're on the main branch and up to date
git checkout main
git pull origin main

# 2. Create and push a new tag
git tag v1.0.0
git push origin v1.0.0

# 3. The GitHub Action will automatically:
#    - Run tests and quality checks
#    - Create a GitHub release
#    - Update Packagist (if configured)
```

### Method 2: GitHub Web Interface

1. Go to your repository on GitHub
2. Click **"Releases"** on the right sidebar
3. Click **"Create a new release"**
4. In **"Choose a tag"**, type your new version (e.g., `v1.0.0`)
5. Select **"Create new tag: v1.0.0 on publish"**
6. Add release title and description
7. Click **"Publish release"**

### Method 3: GitHub CLI

```bash
# Create a release with GitHub CLI
gh release create v1.0.0 --title "Release v1.0.0" --notes "Initial release"
```

## 📊 Workflow Details

### What Happens When You Create a Tag:

1. **Quality Assurance**:
   - Installs dependencies with Composer
   - Runs PHPUnit tests
   - Runs PHPStan static analysis
   - Fails if any checks don't pass

2. **Release Creation**:
   - Generates changelog from git commits since last tag
   - Creates GitHub release with formatted notes
   - Includes installation instructions

3. **Package Publishing**:
   - Triggers Packagist update via API
   - Makes new version available via Composer immediately

### Example Release Notes Format:

```markdown
## What's Changed in v1.0.0

### Commits since v0.9.0:
- Add new schema analysis features
- Fix type detection for decimal columns
- Update documentation

## Installation

```bash
composer require dvrtech/schema-tools:v1.0.0
```

**Full Changelog**: https://github.com/dvrtech-us/laravel-schema-tools/compare/v0.9.0...v1.0.0
```

## 🔄 Version Management

### Semantic Versioning

Follow [semantic versioning](https://semver.org/):

- **v1.0.0** - Major release (breaking changes)
- **v1.1.0** - Minor release (new features, backward compatible)
- **v1.1.1** - Patch release (bug fixes)

### Pre-release Versions

For beta/alpha releases:

```bash
git tag v1.0.0-beta.1
git push origin v1.0.0-beta.1
```

## 🛠️ Troubleshooting

### Common Issues:

1. **Tests Fail During Release**:
   - The workflow will fail if tests don't pass
   - Fix tests locally and create a new tag

2. **Packagist Not Updating**:
   - Check if `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` secrets are set
   - Verify the token has the correct permissions
   - Manual fallback: Update manually on Packagist.org

3. **Release Not Created**:
   - Ensure tag follows the pattern `v*` (e.g., `v1.0.0`)
   - Check GitHub Actions tab for error details

### Manual Packagist Update:

If automatic updates fail:

1. Go to [Packagist.org](https://packagist.org/packages/dvrtech/schema-tools)
2. Click **"Update"** button
3. Or set up GitHub webhook for automatic updates

## 📈 Monitoring Releases

### View Release Status:

1. **GitHub Actions**: Repository → Actions tab
2. **Releases**: Repository → Releases section  
3. **Packagist**: Check [package page](https://packagist.org/packages/dvrtech/schema-tools)

### Release Notifications:

- GitHub will notify watchers of new releases
- Packagist users can "star" your package for updates
- Consider announcing major releases in Laravel communities

## 🎯 Best Practices

1. **Always test before tagging**:
   ```bash
   composer test
   ./vendor/bin/phpstan
   ```

2. **Update CHANGELOG.md** before releases

3. **Use descriptive commit messages** (they appear in auto-generated changelogs)

4. **Test the release process** with pre-release versions first

5. **Monitor release success** in GitHub Actions

---

This automated workflow ensures consistent, tested releases while maintaining professional package distribution standards.
