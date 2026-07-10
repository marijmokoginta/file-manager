#!/usr/bin/env bash
set -euo pipefail

# ─── Version bump: major | minor | patch ───────────────────────────────────────
VERSION_BUMP="${1:-patch}"

if [[ ! "$VERSION_BUMP" =~ ^(major|minor|patch)$ ]]; then
    echo "Error: version param must be one of: major, minor, patch"
    echo "Usage: $0 {major|minor|patch}"
    exit 1
fi

# ─── Detect current branch ─────────────────────────────────────────────────────
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

if [[ "$CURRENT_BRANCH" == "develop" ]]; then
    TAG_SUFFIX="-alpha"
elif [[ "$CURRENT_BRANCH" == "main" ]]; then
    TAG_SUFFIX=""
else
    echo "Error: this script must be run from the 'main' or 'develop' branch."
    echo "Current branch: $CURRENT_BRANCH"
    exit 1
fi

# ─── Fetch latest tag ──────────────────────────────────────────────────────────
git fetch --tags --quiet 2>/dev/null || true

LATEST_TAG=$(git tag --list --sort=-v:refname 'v*' 2>/dev/null | head -1)
if [[ -z "$LATEST_TAG" ]]; then
    LATEST_TAG="v0.0.0"
fi

# ─── Parse version ─────────────────────────────────────────────────────────────
# Strip leading 'v' and any existing suffix (e.g. -alpha)
CLEAN_VERSION=$(echo "${LATEST_TAG#v}" | sed -E 's/-.*$//')

IFS='.' read -r MAJOR MINOR PATCH <<< "$CLEAN_VERSION"

# Default to 0 for unset values
MAJOR="${MAJOR:-0}"
MINOR="${MINOR:-0}"
PATCH="${PATCH:-0}"

# ─── Increment version ─────────────────────────────────────────────────────────
case "$VERSION_BUMP" in
    major)
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        ;;
    minor)
        MINOR=$((MINOR + 1))
        PATCH=0
        ;;
    patch)
        PATCH=$((PATCH + 1))
        ;;
esac

NEW_TAG="v${MAJOR}.${MINOR}.${PATCH}${TAG_SUFFIX}"

# ─── Summary ───────────────────────────────────────────────────────────────────
echo "──────────────────────────────────────────"
echo "  Current branch : $CURRENT_BRANCH"
echo "  Latest tag     : $LATEST_TAG"
echo "  Version bump   : $VERSION_BUMP"
echo "  New tag        : $NEW_TAG"
echo "──────────────────────────────────────────"

# ─── Confirmation ──────────────────────────────────────────────────────────────
read -r -p "Create and push tag \"${NEW_TAG}\"? [y/N] " CONFIRM

if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

# ─── Create and push tag ───────────────────────────────────────────────────────
git tag "$NEW_TAG"
git push origin "$NEW_TAG"

echo ""
echo "Tag \"${NEW_TAG}\" created and pushed to origin."
echo "GitHub Actions will now trigger the release workflow."
