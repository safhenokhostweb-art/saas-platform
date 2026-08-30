#!/usr/bin/env bash
# Fails the job BEFORE upload-artifact runs if a build output has grown
# past its expected ceiling — e.g. an accidental node_modules/vendor/cache
# directory getting swept into a release artifact. Each call site owns its
# own baseline; there is deliberately no single repo-wide size cap (see
# storage-maintenance section 8 of the audit).
#
# Usage: check-artifact-size.sh <label> <max-mb> <path...>
set -euo pipefail

label="$1"
max_mb="$2"
shift 2
paths=("$@")

if [ "${#paths[@]}" -eq 0 ]; then
  echo "::error::check-artifact-size: no paths given for '$label'"
  exit 1
fi

total_kb=0
existing_paths=()
for p in "${paths[@]}"; do
  if [ -e "$p" ]; then
    existing_paths+=("$p")
    kb=$(du -sk "$p" 2>/dev/null | cut -f1)
    total_kb=$((total_kb + kb))
  fi
done

if [ "${#existing_paths[@]}" -eq 0 ]; then
  echo "::warning::check-artifact-size: none of the given paths exist yet for '$label' — skipping guard (nothing built)."
  exit 0
fi

total_mb=$((total_kb / 1024))
echo "Artifact '$label' actual size: ${total_mb}MB (allowed: ${max_mb}MB)"

if [ "$total_mb" -gt "$max_mb" ]; then
  echo "::error::Artifact '$label' is ${total_mb}MB, exceeding the ${max_mb}MB guard."
  echo "Largest top-level contributors:"
  for p in "${existing_paths[@]}"; do
    du -sh "$p"/* 2>/dev/null | sort -rh | head -10 || du -sh "$p"
  done
  exit 1
fi

echo "OK — '$label' within size guard."
