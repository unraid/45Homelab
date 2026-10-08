#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
TEMPLATE="${ROOT_DIR}/45d-drivemap.plg.template"
TMP_DIR=$(mktemp -d)
WORKER_PID=""

cleanup() {
  if [ -n "${WORKER_PID}" ]; then
    kill "${WORKER_PID}" 2>/dev/null || true
    wait "${WORKER_PID}" 2>/dev/null || true
  fi
  rm -rf "${TMP_DIR}"
}
trap cleanup EXIT

extract_stop_function() {
  local occurrence="$1"
  awk -v wanted="${occurrence}" '
    /^stop_rgb_worker\(\) \{/ {
      found++
      capture = found == wanted
    }
    capture { print }
    capture && /^}$/ { exit }
  ' "${TEMPLATE}"
}

INSTALL_FUNCTION=$(extract_stop_function 1)
REMOVE_FUNCTION=$(extract_stop_function 2)
[ -n "${INSTALL_FUNCTION}" ]
eval "${INSTALL_FUNCTION}"

run_successful_shutdown() {
  local proc_root="${TMP_DIR}/proc-${RANDOM}"
  local state_dir="${TMP_DIR}/state-${RANDOM}"
  mkdir -p "${proc_root}" "${state_dir}"

  sleep 30 &
  WORKER_PID=$!
  mkdir -p "${proc_root}/${WORKER_PID}"
  printf '45d-rgb-stream.php\0' > "${proc_root}/${WORKER_PID}/cmdline"
  printf '%s\n' "${WORKER_PID}" > "${state_dir}/rgb-stream.pid"
  (
    sleep 0.1
    rm -rf -- "${proc_root:?}/${WORKER_PID:?}"
  ) &
  FAKE_EXIT_PID=$!

  stop_rgb_worker "${state_dir}/rgb-stream.pid" "${proc_root}"
  wait "${FAKE_EXIT_PID}"
  [ ! -e "${state_dir}/rgb-stream.pid" ]
  WORKER_PID=""
}

run_blocked_shutdown() {
  local proc_root="${TMP_DIR}/blocked-proc"
  local state_dir="${TMP_DIR}/blocked-state"
  local status
  local error_file="${state_dir}/error"
  mkdir -p "${proc_root}" "${state_dir}"

  sleep 30 &
  WORKER_PID=$!
  mkdir -p "${proc_root}/${WORKER_PID}"
  printf '45d-rgb-stream.php\0' > "${proc_root}/${WORKER_PID}/cmdline"
  printf '%s\n' "${WORKER_PID}" > "${state_dir}/rgb-stream.pid"

  set +e
  stop_rgb_worker "${state_dir}/rgb-stream.pid" "${proc_root}" 2>"${error_file}"
  status=$?
  set -e

  [ "${status}" -eq 1 ]
  [ -e "${state_dir}/rgb-stream.pid" ]
  grep -Fq 'aborting plugin update' "${error_file}"
  rm -rf -- "${proc_root:?}"
  WORKER_PID=""
}

run_successful_shutdown
run_blocked_shutdown
eval "${REMOVE_FUNCTION}"
run_successful_shutdown

echo "Plugin lifecycle worker shutdown test passed."
