#!/bin/bash

INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["tool_input"]["file_path"] ?? "";')

if [[ -z "$FILE_PATH" ]]; then
    exit 0
fi

if [[ "$FILE_PATH" != *.php ]]; then
    exit 0
fi

if command -v vendor/bin/pint &>/dev/null; then
    vendor/bin/pint "$FILE_PATH" --quiet 2>/dev/null
fi

exit 0
