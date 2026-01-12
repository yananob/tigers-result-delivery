#!/bin/bash
set -eu

source ./tests/secrets.sh
source ./_cf-common/test/export_secrets.sh ${SECRETS[*]}

# run tests
bash ./tests/run_linter.sh

TEST_TARGET="Unit Integration"
if [ $# -eq 1 ];then
    TEST_TARGET=$1
fi

for suite in $TEST_TARGET; do
    echo "Running PHPUnit ${suite}..."
    ./vendor/bin/phpunit --testsuite ${suite}
done

source ./_cf-common/test/unset_secrets.sh ${SECRETS[*]}
