#!/usr/bin/env bash
set -Eeuo pipefail

readonly SFTP_USER='easytire-ndi'
readonly SFTP_GROUP='easytire-ndi-feed'
readonly CHROOT_ROOT='/srv/sftp/easytire-ndi'
readonly INBOX_PATH="${CHROOT_ROOT}/incoming"
readonly SSH_CONFIG_SOURCE='/root/shop-ndi-deploy/easytire-ndi-sftp.conf'
readonly SSH_CONFIG_TARGET='/etc/ssh/sshd_config.d/90-easytire-ndi-sftp.conf'
readonly CREDENTIAL_FILE='/root/easytire-ndi-sftp-credentials.txt'

if [[ ! -f "${SSH_CONFIG_SOURCE}" ]]; then
    echo 'Reviewed SFTP sshd configuration is missing.' >&2
    exit 1
fi

getent group "${SFTP_GROUP}" >/dev/null || groupadd --system "${SFTP_GROUP}"

if ! id "${SFTP_USER}" >/dev/null 2>&1; then
    useradd --gid "${SFTP_GROUP}" --home-dir /incoming --shell /usr/sbin/nologin "${SFTP_USER}"
    password="$(openssl rand -hex 24)"
    printf '%s:%s\n' "${SFTP_USER}" "${password}" | chpasswd
    umask 077
    {
        printf 'Host: 95.217.153.189\n'
        printf 'Port: 22\n'
        printf 'Protocol: SFTP\n'
        printf 'Username: %s\n' "${SFTP_USER}"
        printf 'Password: %s\n' "${password}"
        printf 'Remote path: /incoming\n'
    } > "${CREDENTIAL_FILE}"
    unset password
fi

install -d -o root -g root -m 0755 "${CHROOT_ROOT}"
install -d -o "${SFTP_USER}" -g www-data -m 2750 "${INBOX_PATH}"
install -o root -g root -m 0644 "${SSH_CONFIG_SOURCE}" "${SSH_CONFIG_TARGET}"

if ! sshd -t; then
    rm -f -- "${SSH_CONFIG_TARGET}"
    echo 'sshd rejected the SFTP configuration; it was removed.' >&2
    exit 1
fi

systemctl reload ssh

effective="$(sshd -T -C user=${SFTP_USER},host=localhost,addr=127.0.0.1)"
grep -q '^passwordauthentication yes$' <<< "${effective}"
grep -q "^chrootdirectory ${CHROOT_ROOT}$" <<< "${effective}"
grep -q '^forcecommand internal-sftp -d /incoming -u 0027$' <<< "${effective}"

echo "Restricted NDI SFTP inbox is ready at ${INBOX_PATH}."
echo "Credentials are stored root-only in ${CREDENTIAL_FILE}."
