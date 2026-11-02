const tokenKey = 'user_access_token';
let csrfRequest = null;

export function getUserToken() {
    return localStorage.getItem(tokenKey);
}

export function saveUserToken(token) {
    localStorage.setItem(tokenKey, token);
}

export function clearUserToken() {
    localStorage.removeItem(tokenKey);
}

function getCookie(name) {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith(`${name}=`));

    return cookie
        ? decodeURIComponent(cookie.slice(name.length + 1))
        : '';
}

async function ensureCsrfCookie() {
    if (getCookie('XSRF-TOKEN')) {
        return;
    }

    if (!csrfRequest) {
        csrfRequest = fetch('/sanctum/csrf-cookie', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('获取 CSRF cookie 失败。');
                }
            })
            .finally(() => {
                csrfRequest = null;
            });
    }

    await csrfRequest;

    if (!getCookie('XSRF-TOKEN')) {
        throw new Error('服务器未返回 XSRF-TOKEN cookie。');
    }
}

export async function userApiRequest(path, options = {}) {
    const method = (options.method ?? 'GET').toUpperCase();
    const pathname = new URL(path, window.location.origin).pathname;
    const isPublicAuthRequest = [
        '/api/auth/login',
        '/api/auth/register',
        '/api/auth/forgot-password',
        '/api/auth/reset-password',
    ].includes(pathname);

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        await ensureCsrfCookie();
    }

    const token = isPublicAuthRequest ? null : getUserToken();
    const xsrfToken = getCookie('XSRF-TOKEN');

    const response = await fetch(path, {
        ...options,
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
            ...(xsrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(method)
                ? { 'X-XSRF-TOKEN': xsrfToken }
                : {}),
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const validationMessage = Object.values(data.errors ?? {})
            .flat()
            .find((message) => typeof message === 'string');

        const error = new Error(
            validationMessage ?? data.message ?? `请求失败（${response.status}）`,
        );
        error.status = response.status;
        error.errors = data.errors ?? {};

        if (response.status === 401 && token) {
            clearUserToken();
            window.dispatchEvent(new Event('user-auth-expired'));
        }

        throw error;
    }

    return data;
}
