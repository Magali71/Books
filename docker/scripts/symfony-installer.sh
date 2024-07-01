#!/usr/bin/env bash
set -o errexit
set -o errtrace
set -o nounset
set -o pipefail

function output {
    style_start=""
    style_end=""
    if [ "${2:-}" != "" ]; then
    case $2 in
        "success")
            style_start="\033[0;32m"
            style_end="\033[0m"
            ;;
        "error")
            style_start="\033[31;31m"
            style_end="\033[0m"
            ;;
        "info")
            style_start="\033[34m"
            style_end="\033[0m"
            ;;
        "warning")
            style_start="\033[33m"
            style_end="\033[39m"
            ;;
        "heading")
            style_start="\033[1;33m"
            style_end="\033[22;39m"
            ;;
    esac
    fi

    builtin echo -e "${style_start}${1}${style_end}"
}


if [ ! -f composer.json ]; then

    symfony new tmp --version=$SYMFONY_VERSION --no-git

    cd tmp
    composer require "php:>=$PHP_VERSION"
    composer config --json extra.symfony.docker 'false'
    composer config --json extra.symfony.allow-contrib 'true'
    composer require --dev phpstan/phpstan-symfony squizlabs/php_codesniffer php-parallel-lint/php-parallel-lint
    cp -Rp . ..
    cd -

    rm -Rf tmp/

    output "Don't forget to modify your README.md" "warning"
    output "Don't forget to configure your composer.json" "warning"

fi

