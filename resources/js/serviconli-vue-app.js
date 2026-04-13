import { createApp, h } from 'vue';

import LoginPage from './serviconli/vue/pages/LoginPage.vue';
import MisAfiliadosPage from './serviconli/vue/pages/MisAfiliadosPage.vue';
import FichaPage from './serviconli/vue/pages/FichaPage.vue';
import AporteIndividualPage from './serviconli/vue/pages/AporteIndividualPage.vue';
import LiquidacionLotesPage from './serviconli/vue/pages/LiquidacionLotesPage.vue';
import GenerarPILAPage from './serviconli/vue/pages/GenerarPILAPage.vue';
import CarteraPage from './serviconli/vue/pages/CarteraPage.vue';
import CuadreCajaPage from './serviconli/vue/pages/CuadreCajaPage.vue';
import AsesoresPage from './serviconli/vue/pages/AsesoresPage.vue';
import TercerosPage from './serviconli/vue/pages/TercerosPage.vue';
import DashboardPage from './serviconli/vue/pages/DashboardPage.vue';
import DocumentosPage from './serviconli/vue/pages/DocumentosPage.vue';

const PAGE_COMPONENT = {
    login: LoginPage,
    'mis-afiliados': MisAfiliadosPage,
    ficha: FichaPage,
    'aporte-individual': AporteIndividualPage,
    'liquidacion-lotes': LiquidacionLotesPage,
    'generar-pila': GenerarPILAPage,
    cartera: CarteraPage,
    'cuadre-caja': CuadreCajaPage,
    asesores: AsesoresPage,
    terceros: TercerosPage,
    dashboard: DashboardPage,
    documentos: DocumentosPage,
};

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('serviconli-vue-root');
    if (!root) {
        return;
    }

    const page = document.body.dataset.page;
    const Comp = PAGE_COMPONENT[page];
    if (!Comp) {
        return;
    }

    const props = {
        affiliateId: root.dataset.affiliateId || null,
    };

    const app = createApp({
        render: () => h(Comp, props),
    });
    app.mount(root);
});
