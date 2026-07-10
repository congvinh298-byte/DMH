#!/bin/bash
# Script chạy TRÊN HOST dienmayhieu.com để pull code mới nhất từ GitHub
# Yêu cầu: host đã cài git và clone repo DMH

REPO_URL="https://github.com/congvinh298-byte/DMH.git"
WEB_ROOT="${WEB_ROOT:-/home/kwkrbcce/public_html}"
BRANCH="main"

echo "=== Deploy DienMayHieu from GitHub ==="
echo "WEB_ROOT: ${WEB_ROOT}"

if [ ! -d "${WEB_ROOT}/.git" ]; then
    echo "Chua co git repo trong ${WEB_ROOT}. Tien hanh clone..."
    git clone -b "${BRANCH}" "${REPO_URL}" "${WEB_ROOT}"
else
    cd "${WEB_ROOT}" || exit 1
    echo "Pull code moi nhat..."
    git fetch origin
    git reset --hard "origin/${BRANCH}"
    git clean -fd
fi

echo ""
echo "=== Kiem tra .env ==="
if [ ! -f "${WEB_ROOT}/.env" ]; then
    echo "CAU HINH: File .env chua co tren host. Vui long upload .env thu cong qua cPanel File Manager."
    exit 1
fi

echo ""
echo "=== Cap quyen thu muc uploads (neu can) ==="
mkdir -p "${WEB_ROOT}/uploads"
chmod 755 "${WEB_ROOT}/uploads"

echo ""
echo "=== Hoan tat ==="
echo "Hay truy cap: https://dienmayhieu.com/api_master.php?action=health"
