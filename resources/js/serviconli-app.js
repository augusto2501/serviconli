/**
 * UI web Serviconli: token Sanctum en sessionStorage (misma origen que /api).
 * Páginas: data-page en <body> → login | mis-afiliados | ficha
 */

const TOKEN_KEY = 'serviconli_api_token';

function getToken() {
    return sessionStorage.getItem(TOKEN_KEY);
}

function setToken(t) {
    sessionStorage.setItem(TOKEN_KEY, t);
}

function clearToken() {
    sessionStorage.removeItem(TOKEN_KEY);
}

function requireAuth() {
    if (!getToken()) {
        window.location.href = '/login';
        return false;
    }
    return true;
}

async function apiFetch(path, options = {}) {
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

function initLogin() {
    if (getToken()) {
        window.location.replace('/mis-afiliados');
        return;
    }
    const form = document.getElementById('login-form');
    const err = document.getElementById('login-error');
    if (!form) {
        return;
    }
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        err.textContent = '';
        err.classList.add('hidden');
        const fd = new FormData(form);
        const body = {
            email: fd.get('email'),
            password: fd.get('password'),
        };
        try {
            const res = await fetch('/api/login', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                err.textContent = data.message || data.errors?.email?.[0] || 'Credenciales incorrectas.';
                err.classList.remove('hidden');
                return;
            }
            if (data.token) {
                setToken(data.token);
            }
            window.location.href = '/mis-afiliados';
        } catch {
            err.textContent = 'No se pudo conectar. Intente de nuevo.';
            err.classList.remove('hidden');
        }
    });
}

function initMisAfiliados() {
    if (!requireAuth()) {
        return;
    }
    const tbody = document.getElementById('affiliates-tbody');
    const metaEl = document.getElementById('affiliates-meta');
    const qInput = document.getElementById('filter-q');
    const btnSearch = document.getElementById('btn-search');
    const btnExportCsv = document.getElementById('btn-export-csv');
    const btnExportXlsx = document.getElementById('btn-export-xlsx');
    const btnLogout = document.getElementById('btn-logout');

    async function load(page = 1) {
        const q = qInput?.value?.trim() || '';
        const params = new URLSearchParams({ page: String(page), per_page: '15' });
        if (q) {
            params.set('q', q);
        }
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-stone-500">Cargando…</td></tr>`;
        try {
            const res = await apiFetch(`/affiliates?${params.toString()}`);
            const data = await res.json();
            const rows = data.data || [];
            metaEl.textContent = `Total: ${data.meta?.total ?? 0} · Página ${data.meta?.currentPage ?? 1} de ${data.meta?.lastPage ?? 1}`;
            if (rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-stone-500">Sin resultados.</td></tr>`;
                return;
            }
            tbody.innerHTML = rows
                .map(
                    (a) => `
                <tr class="border-t border-stone-200/80 hover:bg-white/60">
                    <td class="px-4 py-3 font-mono text-sm">${escapeHtml(a.documentNumber ?? '—')}</td>
                    <td class="px-4 py-3">${escapeHtml((a.fullName ?? [a.firstName, a.lastName].filter(Boolean).join(' ')) || '—')}</td>
                    <td class="px-4 py-3 text-sm">${escapeHtml(a.clientType ?? '—')}</td>
                    <td class="px-4 py-3 text-sm">${escapeHtml(a.moraStatus ?? '—')}</td>
                    <td class="px-4 py-3 text-sm max-w-[10rem] truncate" title="${escapeHtml(a.epsName ?? '')}">${escapeHtml(a.epsName ?? '—')}</td>
                    <td class="px-4 py-3 text-right">
                        <a class="text-teal-800 font-medium hover:underline" href="/afiliados/${a.id}/ficha">Ficha 360°</a>
                    </td>
                </tr>`,
                )
                .join('');
        } catch {
            tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-red-700">Error al cargar.</td></tr>`;
        }
    }

    btnSearch?.addEventListener('click', () => load(1));
    qInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            load(1);
        }
    });

    async function downloadExport(format) {
        const token = getToken();
        if (!token) {
            return;
        }
        const res = await fetch(`/api/affiliates/export?format=${encodeURIComponent(format)}`, {
            headers: { Authorization: `Bearer ${token}`, Accept: '*/*' },
        });
        if (res.status === 401) {
            clearToken();
            window.location.href = '/login';
            return;
        }
        if (!res.ok) {
            alert('No se pudo exportar.');
            return;
        }
        const blob = await res.blob();
        const cd = res.headers.get('Content-Disposition');
        let filename = `export-afiliados.${format === 'xlsx' ? 'xlsx' : 'csv'}`;
        if (cd && cd.includes('filename=')) {
            const m = cd.match(/filename="?([^";]+)"?/);
            if (m) {
                filename = m[1];
            }
        }
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    }

    btnExportCsv?.addEventListener('click', () => downloadExport('csv'));
    btnExportXlsx?.addEventListener('click', () => downloadExport('xlsx'));

    btnLogout?.addEventListener('click', async () => {
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
    });

    load(1);
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function initFicha() {
    if (!requireAuth()) {
        return;
    }
    const root = document.getElementById('ficha-root');
    const id = root?.dataset?.affiliateId;
    const quick = document.getElementById('quick-actions');
    if (!id || !root) {
        return;
    }

    quick?.querySelectorAll('[data-quick-action]').forEach((btn) => {
        btn.addEventListener('click', () => {
            alert('Próximamente: este flujo se conectará al módulo correspondiente (RF-016).');
        });
    });

    (async () => {
        root.innerHTML = '<p class="text-stone-500 py-6">Cargando ficha…</p>';
        try {
            const res = await apiFetch(`/affiliates/${id}/ficha-360`);
            const j = await res.json();
            root.innerHTML = renderFicha(j);
        } catch {
            root.innerHTML = '<p class="text-red-700 py-6">No se pudo cargar la ficha.</p>';
        }
    })();
}

function renderFicha(j) {
    const p = j.person || {};
    const a = j.affiliate || {};
    const notes = (j.notes?.recent || []).length;
    const ben = j.counts?.beneficiaries ?? 0;
    return `
        <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-xl border border-stone-200/90 bg-white/80 p-5 shadow-sm">
                <h2 class="font-serif text-lg font-bold text-stone-900 mb-3">Persona</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-stone-500 inline">Documento</dt> <dd class="inline font-mono">${escapeHtml(String(p.documentNumber ?? '—'))}</dd></div>
                    <div><dt class="text-stone-500 inline">Nombre</dt> <dd class="inline">${escapeHtml([p.firstName, p.secondName, p.firstSurname, p.secondSurname].filter(Boolean).join(' ') || '—')}</dd></div>
                </dl>
            </section>
            <section class="rounded-xl border border-stone-200/90 bg-white/80 p-5 shadow-sm">
                <h2 class="font-serif text-lg font-bold text-stone-900 mb-3">Afiliado</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-stone-500 inline">Estado</dt> <dd class="inline">${escapeHtml(String(a.statusName ?? a.statusCode ?? '—'))}</dd></div>
                    <div><dt class="text-stone-500 inline">Mora</dt> <dd class="inline">${escapeHtml(String(a.moraStatus ?? '—'))}</dd></div>
                    <div><dt class="text-stone-500 inline">Tipo cliente</dt> <dd class="inline">${escapeHtml(String(a.clientType ?? '—'))}</dd></div>
                </dl>
            </section>
        </div>
        <section class="rounded-xl border border-stone-200/90 bg-white/80 p-5 shadow-sm mt-6">
            <h2 class="font-serif text-lg font-bold text-stone-900 mb-2">Resumen</h2>
            <p class="text-sm text-stone-600">Beneficiarios: <strong>${ben}</strong> · Notas recientes: <strong>${notes}</strong></p>
            <p class="text-sm text-stone-500 mt-2">Liquidaciones PILA, facturas y portales: ver JSON en API para detalle completo.</p>
        </section>
    `;
}

document.addEventListener('DOMContentLoaded', () => {
    const page = document.body.dataset.page;
    if (page === 'login') {
        initLogin();
    }
    if (page === 'mis-afiliados') {
        initMisAfiliados();
    }
    if (page === 'ficha') {
        initFicha();
    }
});
