const TOKEN_KEY = 'serviconli_api_token';

export function getToken() {
    return sessionStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    sessionStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
    sessionStorage.removeItem(TOKEN_KEY);
}

export function requireAuth() {
    if (!getToken()) {
        window.location.href = '/login';
        return false;
    }
    return true;
}

export async function apiFetch(path, options = {}) {
    const url = path.startsWith('http') ? path : `/api${path.startsWith('/') ? path : `/${path}`}`;
    const headers = {
        Accept: 'application/json',
        ...options.headers,
    };
    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    const res = await fetch(url, { ...options, headers });
    if (res.status === 401) {
        clearToken();
        window.location.href = '/login';
        throw new Error('No autenticado');
    }
    return res;
}

export async function logoutAndRedirect() {
    const token = getToken();
    if (token) {
        await fetch('/api/logout', {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        }).catch(() => {});
    }
    clearToken();
    window.location.href = '/login';
}
