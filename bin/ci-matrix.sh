#!/usr/bin/env bash
# Mimics CircleCI matrix builds locally using Docker.
# Runs each Sylius/Symfony combo with both --prefer-lowest and --prefer-dist.
#
# Usage:
#   ./bin/ci-matrix.sh                    # run all combos
#   ./bin/ci-matrix.sh 2.2 7.4            # run single combo (sylius symfony)
#   ./bin/ci-matrix.sh 2.0 6.4 lowest     # run single combo, only prefer-lowest

set -euo pipefail
IFS=$'\n\t'
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$(dirname "$DIR")"

# Define the matrix: sylius_version:symfony_version
ALL_COMBOS=(
    "2.0:6.4"
    "2.0:7.1"
    "2.1:6.4"
    "2.1:7.2"
    "2.2:6.4"
    "2.2:7.4"
)

FILTER_SYLIUS="${1:-}"
FILTER_SYMFONY="${2:-}"
FILTER_STRATEGY="${3:-}"  # "lowest", "dist", or empty for both

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

RESULTS=()

run_combo() {
    local sylius_version="$1"
    local symfony_version="$2"
    local strategy="$3"  # prefer-lowest or prefer-dist
    local label="sylius-${sylius_version}-symfony-${symfony_version}-${strategy}"

    echo ""
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}  ${label}${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    # Pin Sylius version
    ./bin-docker/composer require "sylius/sylius:${sylius_version}.*" --no-interaction --no-update --no-scripts

    # Pin Symfony version for all symfony/* packages (except flex and webpack-encore-bundle)
    local symfony_packages
    symfony_packages=$(grep -o -E '"(symfony/[^"]+)"' composer.json | grep -v -E '(symfony/flex|symfony/webpack-encore-bundle)' | xargs printf '%s:'"${symfony_version}"'.* ')
    # shellcheck disable=SC2086
    ./bin-docker/composer require ${symfony_packages} --no-interaction --no-update

    # Install dependencies
    rm -f composer.lock
    if [ "$strategy" = "prefer-lowest" ]; then
        ./bin-docker/composer update --no-interaction --prefer-lowest --no-plugins
    else
        ./bin-docker/composer update --no-interaction --prefer-dist --no-plugins
    fi

    # Prepare test environment
    make var
    ./bin-docker/php ./bin/console --env=test cache:clear
    ./bin-docker/php ./bin/console --env=test cache:warmup
    ./bin-docker/php ./bin/console --env=test doctrine:database:create --if-not-exists
    ./bin-docker/php ./bin/console --env=test doctrine:schema:update --force --complete
    ./bin-docker/php ./bin/console --env=test assets:install
    ./bin-docker/yarn --cwd=tests/Application install --pure-lockfile
    GULP_ENV=prod ./bin-docker/yarn --cwd=tests/Application build
    ./bin-docker/php ./bin/console --env=test sylius:payment:generate-key --overwrite --quiet
    ./bin-docker/php ./bin/console --env=test lexik:jwt:generate-keypair --overwrite --quiet

    # Run checks
    ./bin-docker/docker-bash bin/phpstan.sh
    ./bin-docker/docker-bash bin/ecs.sh --clear-cache
    ./bin-docker/docker-bash bin/symfony-lint.sh
    ./bin-docker/docker-bash bin/behat.sh

    echo -e "${GREEN}✅ PASSED: ${label}${NC}"
}

run_combo_safe() {
    local sylius_version="$1"
    local symfony_version="$2"
    local strategy="$3"
    local label="sylius-${sylius_version}-symfony-${symfony_version}-${strategy}"

    if run_combo "$sylius_version" "$symfony_version" "$strategy"; then
        RESULTS+=("${GREEN}✅ PASSED: ${label}${NC}")
    else
        RESULTS+=("${RED}❌ FAILED: ${label}${NC}")
    fi

    # Restore composer.json from git so next combo starts clean
    git checkout -- composer.json
}

# Build list of combos to run
COMBOS_TO_RUN=()
for combo in "${ALL_COMBOS[@]}"; do
    sylius="${combo%%:*}"
    symfony="${combo##*:}"

    if [ -n "$FILTER_SYLIUS" ] && [ "$FILTER_SYLIUS" != "$sylius" ]; then
        continue
    fi
    if [ -n "$FILTER_SYMFONY" ] && [ "$FILTER_SYMFONY" != "$symfony" ]; then
        continue
    fi
    COMBOS_TO_RUN+=("$combo")
done

if [ ${#COMBOS_TO_RUN[@]} -eq 0 ]; then
    echo "No matching combos found for filter: sylius=${FILTER_SYLIUS} symfony=${FILTER_SYMFONY}"
    exit 1
fi

# Run each combo
for combo in "${COMBOS_TO_RUN[@]}"; do
    sylius="${combo%%:*}"
    symfony="${combo##*:}"

    if [ -z "$FILTER_STRATEGY" ] || [ "$FILTER_STRATEGY" = "lowest" ]; then
        run_combo_safe "$sylius" "$symfony" "prefer-lowest"
    fi
    if [ -z "$FILTER_STRATEGY" ] || [ "$FILTER_STRATEGY" = "dist" ]; then
        run_combo_safe "$sylius" "$symfony" "prefer-dist"
    fi
done

# Summary
echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  SUMMARY${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
for result in "${RESULTS[@]}"; do
    echo -e "  $result"
done
echo ""
