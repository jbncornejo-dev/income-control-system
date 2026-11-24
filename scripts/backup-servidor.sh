#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

usage() {
    cat >&2 <<'EOF'
Uso:
  bash scripts/backup-servidor.sh crear
  bash scripts/backup-servidor.sh restaurar /ruta/backup-... nueva_base /ruta/fotos-restauradas

Variables para crear: BACKUP_DIR, PUBLIC_STORAGE_DIR, PGDATABASE.
Conexión: PGHOST, PGPORT, PGUSER, PGPASSFILE y las demás variables de libpq.
Opcional: BACKUP_RETENTION_DAYS (14 por defecto, 0 para conservar todos).
EOF
    exit 2
}

fail() {
    printf '%s\n' "$1" >&2
    exit 1
}

manifest() {
    (cd -- "$1" && sha256sum base.dump archivos-publicos.tar.gz)
}

create_backup() {
    : "${BACKUP_DIR:?Indica el directorio privado de backups}"
    : "${PUBLIC_STORAGE_DIR:?Indica el volumen storage/app/public}"
    : "${PGDATABASE:?Indica la base PostgreSQL de la aplicación}"
    local retention="${BACKUP_RETENTION_DAYS:-14}"
    [[ "$retention" =~ ^[0-9]{1,5}$ ]] || fail 'La retención debe expresarse en días.'
    [[ -d "$PUBLIC_STORAGE_DIR" ]] || fail 'No existe el volumen de archivos públicos.'
    local backup_path storage_path
    backup_path=$(realpath -m -- "$BACKUP_DIR")
    storage_path=$(realpath -- "$PUBLIC_STORAGE_DIR")
    [[ "$backup_path" != / && "$storage_path" != / ]] || fail 'Indica directorios dedicados para backups y archivos.'
    case "$backup_path/" in
        "$storage_path/"*) fail 'El directorio de backups debe quedar fuera del volumen público.' ;;
    esac
    BACKUP_DIR="$backup_path"
    if [[ ! -e "$BACKUP_DIR" ]]; then
        install -d -m 700 -- "$BACKUP_DIR"
    fi
    [[ -d "$BACKUP_DIR" && "$(stat -c '%a' -- "$BACKUP_DIR")" == 700 ]] || fail 'El directorio de backups debe tener permisos 0700.'
    exec 9>"$BACKUP_DIR/.backup.lock"
    flock -n 9 || fail 'Ya hay un backup en curso.'

    partial=$(mktemp -d -- "$BACKUP_DIR/.parcial.XXXXXXXX")
    trap 'if [[ -d "$partial" ]]; then rm -f -- "$partial/base.dump" "$partial/archivos-publicos.tar.gz" "$partial/SHA256SUMS"; rmdir -- "$partial"; fi' EXIT

    pg_dump --no-password --format=custom --file="$partial/base.dump"
    pg_restore --list "$partial/base.dump" >/dev/null
    tar -C "$PUBLIC_STORAGE_DIR" -czf "$partial/archivos-publicos.tar.gz" .
    gzip -t "$partial/archivos-publicos.tar.gz"
    manifest "$partial" >"$partial/SHA256SUMS"

    local final="$BACKUP_DIR/backup-$(date -u +%Y%m%dT%H%M%SZ)-${partial##*.}"
    mv -T -- "$partial" "$final"
    printf 'Backup creado en %s\n' "$final"

    if (( 10#$retention > 0 )); then
        local vencido
        while IFS= read -r -d '' vencido; do
            rm -f -- "$vencido/base.dump" "$vencido/archivos-publicos.tar.gz" "$vencido/SHA256SUMS"
            rmdir -- "$vencido"
        done < <(find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name 'backup-????????T??????Z-????????' -mtime "+$((10#$retention - 1))" -print0)
    fi
}

restore_backup() {
    [[ $# == 3 ]] || usage
    local backup="$1" database="$2" uploads="$3"
    [[ -d "$backup" && -f "$backup/SHA256SUMS" ]] || fail 'No se encontró el backup.'
    [[ "$database" =~ ^[a-z][a-z0-9_]{0,62}$ ]] || fail 'Indica un nombre válido para la nueva base.'
    [[ "$database" != "${PGDATABASE:-}" && "$database" != postgres && "$database" != template0 && "$database" != template1 ]] || fail 'Elige una base nueva.'
    [[ "$uploads" == /* && ! -e "$uploads" && ! -L "$uploads" ]] || fail 'Indica una ruta absoluta nueva para los archivos restaurados.'

    manifest "$backup" | cmp -s - "$backup/SHA256SUMS" || fail 'Falló la comprobación de integridad.'
    pg_restore --list "$backup/base.dump" >/dev/null
    gzip -t "$backup/archivos-publicos.tar.gz"

    # createdb protege cualquier base existente. La importación se realiza en
    # una única transacción y el volumen restaurado se prepara aparte.
    createdb --no-password --maintenance-db=postgres --template=template0 "$database"
    pg_restore --no-password --exit-on-error --single-transaction --no-owner --no-privileges \
        --dbname="$database" "$backup/base.dump"

    mkdir -m 700 -- "$uploads"
    tar --no-same-owner --no-same-permissions -C "$uploads" -xzf "$backup/archivos-publicos.tar.gz"
    printf 'Base %s y archivos en %s restaurados. Valídalos antes de activarlos.\n' "$database" "$uploads"
}

case "${1:-}" in
    crear) [[ $# == 1 ]] || usage; create_backup ;;
    restaurar) shift; restore_backup "$@" ;;
    *) usage ;;
esac
