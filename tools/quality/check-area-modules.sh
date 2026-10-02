#!/usr/bin/env bash
set -euo pipefail

DEFAULT_ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ROOT_DIR="${AREA_MODULES_ROOT:-$DEFAULT_ROOT_DIR}"
ALLOWLIST_FILE="${AREA_MODULES_ALLOWLIST:-$ROOT_DIR/tools/quality/area-modules-allowlist.txt}"
SELECTED_MODULE="${AREA_MODULES_MODULE:-}"
SELECTED_PATH="${AREA_MODULES_PATH:-}"
COLLECTION_FACTORY_TYPE='Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory'

cd "$ROOT_DIR"

violations=0

relative_path() {
  local path="$1"

  printf '%s\n' "${path#$ROOT_DIR/}"
}

defines_area_collection_factory_collections() {
  local file="$1"

  awk '
    BEGIN {
      RS = ">"
      in_collection_factory = 0
      found = 0
    }
    {
      tag = $0
      gsub(/[[:space:]]/, "", tag)
      gsub(/\047/, "\"", tag)

      if (index(tag, "<type") > 0 \
          && index(tag, "name=\"Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\CollectionFactory\"") > 0) {
        in_collection_factory = 1
      }

      if (in_collection_factory \
          && index(tag, "<argument") > 0 \
          && index(tag, "name=\"collections\"") > 0) {
        found = 1
        exit
      }

      if (in_collection_factory && index(tag, "</type") > 0) {
        in_collection_factory = 0
      }
    }
    END {
      exit found ? 0 : 1
    }
  ' "$file"
}

check_area_collection_factory_config() {
  local module_dir="$1"
  local explicit_module_name="${2:-}"
  local module_name
  local area_di

  if [ -n "$explicit_module_name" ]; then
    module_name="$explicit_module_name"
  else
    module_name="$(basename "$(dirname "$module_dir")")_$(basename "$module_dir")"
  fi

  if [ ! -d "$module_dir/etc" ]; then
    return
  fi

  while IFS= read -r area_di; do
    if ! defines_area_collection_factory_collections "$area_di"; then
      continue
    fi

    printf 'UI grid DI stage violation: %s declares %s::collections in %s\n' \
      "$module_name" "$COLLECTION_FACTORY_TYPE" "$(relative_path "$area_di")" >&2
    printf '%s\n' \
      '  Cause: Magento area DI replaces this global array argument instead of extending it. Handles from Magento and other modules disappear, so every affected grid can fail with "Not registered handle <data_source>".' >&2
    printf '  Fix: move the CollectionFactory collections items and their grid collection type/virtualType configuration to %s/etc/di.xml. Do not allowlist this violation.\n' \
      "$(relative_path "$module_dir")" >&2
    violations=$((violations + 1))
  done < <(find "$module_dir/etc" -mindepth 2 -maxdepth 2 -type f -name di.xml | sort)
}

module_type() {
  local module="$1"

  case "$module" in
    *Adminhtml|*AdminUi) printf '%s\n' 'adminhtml' ;;
    *GraphQl) printf '%s\n' 'graphql' ;;
    *WebApi) printf '%s\n' 'webapi' ;;
    *FrontendUi) printf '%s\n' 'frontend' ;;
    *Frontend) printf '%s\n' 'frontend' ;;
    *) printf '%s\n' 'base' ;;
  esac
}

base_module_name() {
  local module="$1"

  case "$module" in
    *Adminhtml) printf '%s\n' "${module%Adminhtml}" ;;
    *AdminUi) printf '%s\n' "${module%AdminUi}" ;;
    *GraphQl) printf '%s\n' "${module%GraphQl}" ;;
    *WebApi) printf '%s\n' "${module%WebApi}" ;;
    *FrontendUi) printf '%s\n' "${module%FrontendUi}" ;;
    *Frontend) printf '%s\n' "${module%Frontend}" ;;
    *) printf '%s\n' "$module" ;;
  esac
}

area_suffix() {
  local area="$1"

  case "$area" in
    adminhtml) printf '%s\n' 'AdminUi' ;;
    graphql) printf '%s\n' 'GraphQl' ;;
    webapi) printf '%s\n' 'WebApi' ;;
    frontend) printf '%s\n' 'FrontendUi' ;;
    *) return 1 ;;
  esac
}

suggested_area_module() {
  local vendor="$1"
  local module="$2"
  local area="$3"
  local base

  base="$(base_module_name "$module")"

  if [ "$area" = "adminhtml" ]; then
    printf '%s_%sAdminUi' "$vendor" "$base"
    return
  fi

  printf '%s_%s%s' "$vendor" "$base" "$(area_suffix "$area")"
}

legacy_allows_area() {
  local module="$1"
  local area="$2"
  local key

  if [ ! -f "$ALLOWLIST_FILE" ]; then
    return 1
  fi

  key="$module:$area"

  awk -v key="$key" '
    /^[[:space:]]*($|#)/ { next }
    $1 == key { found = 1 }
    END { exit found ? 0 : 1 }
  ' "$ALLOWLIST_FILE"
}

first_adminhtml_path() {
  local module_dir="$1"

  find "$module_dir" \
    \( -path "$module_dir/etc/adminhtml" \
    -o -path "$module_dir/Controller/Adminhtml" \
    -o -path "$module_dir/Block/Adminhtml" \
    -o -path "$module_dir/Ui" \
    -o -path "$module_dir/view/adminhtml" \) \
    -print -quit
}

first_graphql_path() {
  local module_dir="$1"

  find "$module_dir" \
    \( -path "$module_dir/etc/schema.graphqls" \
    -o \( -path "$module_dir/Model/Resolver/*" -a -type f -a -name '*.php' \) \) \
    -print -quit
}

first_webapi_path() {
  local module_dir="$1"

  find "$module_dir" \
    \( -path "$module_dir/etc/webapi.xml" \
    -o -path "$module_dir/etc/webapi_async.xml" \) \
    -print -quit
}

first_frontend_path() {
  local module_dir="$1"
  local path

  path="$(find "$module_dir" \
    \( -path "$module_dir/etc/frontend" \
    -o -path "$module_dir/view/frontend" \) \
    -print -quit)"
  if [ -n "$path" ]; then
    printf '%s\n' "$path"
    return
  fi

  if [ -d "$module_dir/Controller" ]; then
    path="$(find "$module_dir/Controller" -mindepth 1 -maxdepth 1 ! -name Adminhtml -print -quit)"
    if [ -n "$path" ]; then
      printf '%s\n' "$path"
      return
    fi
  fi

  if [ -d "$module_dir/Block" ]; then
    path="$(find "$module_dir/Block" -mindepth 1 -maxdepth 1 ! -name Adminhtml -print -quit)"
    if [ -n "$path" ]; then
      printf '%s\n' "$path"
    fi
  fi
}

area_path() {
  local module_dir="$1"
  local area="$2"

  case "$area" in
    adminhtml) first_adminhtml_path "$module_dir" ;;
    graphql) first_graphql_path "$module_dir" ;;
    webapi) first_webapi_path "$module_dir" ;;
    frontend) first_frontend_path "$module_dir" ;;
    *) return 1 ;;
  esac
}

allowed_area_for_type() {
  local type="$1"
  local area="$2"

  case "$type:$area" in
    adminhtml:adminhtml|graphql:graphql|webapi:webapi|frontend:frontend) return 0 ;;
    *) return 1 ;;
  esac
}

check_module_area() {
  local module_dir="$1"
  local explicit_module_name="${2:-}"
  local vendor
  local module
  local module_name
  local type
  local area
  local path
  local base
  local shared_module
  local suggested_module

  if [ -n "$explicit_module_name" ]; then
    vendor="${explicit_module_name%%_*}"
    module="${explicit_module_name#*_}"
    module_name="$explicit_module_name"
  else
    vendor="$(basename "$(dirname "$module_dir")")"
    module="$(basename "$module_dir")"
    module_name="${vendor}_${module}"
  fi
  type="$(module_type "$module")"
  base="$(base_module_name "$module")"
  shared_module="${vendor}_${base}"

  if [[ "$module" == *Adminhtml ]]; then
    printf 'Admin module suffix violation: %s uses legacy suffix Adminhtml.\n' "$module_name" >&2
    printf '  Fix: rename the module to %s_%sAdminUi. New admin panel modules must use AdminUi.\n' \
      "$vendor" "$base" >&2
    violations=$((violations + 1))
  fi

  if [[ "$module" == *Frontend ]]; then
    printf 'Frontend module suffix violation: %s uses legacy suffix Frontend.\n' "$module_name" >&2
    printf '  Fix: rename the module to %s_%sFrontendUi. New storefront UI modules must use FrontendUi.\n' \
      "$vendor" "$base" >&2
    violations=$((violations + 1))
  fi

  for area in adminhtml graphql webapi frontend; do
    path="$(area_path "$module_dir" "$area")"
    if [ -z "$path" ]; then
      continue
    fi

    if allowed_area_for_type "$type" "$area" || legacy_allows_area "$module_name" "$area"; then
      continue
    fi

    suggested_module="$(suggested_area_module "$vendor" "$module" "$area")"

    printf 'Area module boundary violation: %s is a %s module but contains %s code at %s\n' \
      "$module_name" "$type" "$area" "${path#$ROOT_DIR/}" >&2
    printf '  Fix: move %s-specific files to %s. Shared contracts and domain logic belong in %s.\n' \
      "$area" "$suggested_module" "$shared_module" >&2
    printf '  Legacy exception: add a reviewed "Vendor_Module:%s reason" entry to %s only for migration debt.\n' \
      "$area" "$(relative_path "$ALLOWLIST_FILE")" >&2
    violations=$((violations + 1))
  done
}

if [ -n "$SELECTED_MODULE" ]; then
  if [[ ! "$SELECTED_MODULE" =~ ^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$ ]]; then
    printf 'Invalid AREA_MODULES_MODULE: %s\n' "$SELECTED_MODULE" >&2
    exit 1
  fi

  selected_dir="${SELECTED_PATH:-app/code/${SELECTED_MODULE/_//}}"
  if [ ! -d "$selected_dir" ]; then
    printf 'Selected module path does not exist: %s\n' "$selected_dir" >&2
    exit 1
  fi
  check_module_area "$selected_dir" "$SELECTED_MODULE"
  check_area_collection_factory_config "$selected_dir" "$SELECTED_MODULE"
else
  for vendor_dir in app/code/Vendivo app/code/PackHauer; do
    if [ ! -d "$vendor_dir" ]; then
      continue
    fi

    while IFS= read -r module_dir; do
      check_module_area "$module_dir"
    done < <(find "$vendor_dir" -mindepth 1 -maxdepth 1 -type d | sort)
  done

  while IFS= read -r module_dir; do
    check_area_collection_factory_config "$module_dir"
  done < <(find app/code -mindepth 2 -maxdepth 2 -type d | sort)
fi

if [ "$violations" -gt 0 ]; then
  printf 'Found %s area module boundary violation(s).\n' "$violations" >&2
  printf 'Legacy allowlist: %s\n' "$(relative_path "$ALLOWLIST_FILE")" >&2
  exit 1
fi

printf 'Area module boundary check passed. Legacy allowlist: %s\n' "$(relative_path "$ALLOWLIST_FILE")"
