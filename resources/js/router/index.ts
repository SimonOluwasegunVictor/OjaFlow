import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import DashboardLayout from '../layouts/DashboardLayout.vue';
import LoginView from '../views/auth/LoginView.vue';
import RegisterView from '../views/auth/RegisterView.vue';
import StaffLoginView from '../views/auth/StaffLoginView.vue';
import BranchesView from '../views/dashboard/BranchesView.vue';
import ComingSoonView from '../views/dashboard/ComingSoonView.vue';
import DashboardHomeView from '../views/dashboard/DashboardHomeView.vue';
import ProfileView from '../views/dashboard/ProfileView.vue';
import StaffView from '../views/dashboard/StaffView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            redirect: { name: 'dashboard' },
        },
        {
            path: '/login',
            name: 'login',
            component: LoginView,
            meta: { guest: true },
        },
        {
            path: '/staff-login',
            name: 'staff-login',
            component: StaffLoginView,
            meta: { guest: true },
        },
        {
            path: '/register',
            name: 'register',
            component: RegisterView,
            meta: { guest: true },
        },
        {
            path: '/',
            component: DashboardLayout,
            meta: { requiresAuth: true },
            children: [
                {
                    path: 'dashboard',
                    name: 'dashboard',
                    component: DashboardHomeView,
                    meta: { title: 'Dashboard' },
                },
                {
                    path: 'branches',
                    name: 'branches',
                    component: BranchesView,
                    meta: { title: 'Branches' },
                },
                {
                    path: 'stock',
                    name: 'stock',
                    component: ComingSoonView,
                    meta: { title: 'Stock', permission: ['view_stock', 'adjust_stock'] },
                },
                {
                    path: 'staff',
                    name: 'staff',
                    component: StaffView,
                    meta: { title: 'Staff' },
                },
                {
                    path: 'profile',
                    name: 'profile',
                    component: ProfileView,
                    meta: { title: 'Settings' },
                },
                {
                    path: 'record-sale',
                    name: 'record-sale',
                    component: ComingSoonView,
                    meta: { title: 'Record Sale', permission: 'record_sales' },
                },
                {
                    path: 'products',
                    name: 'products',
                    component: ComingSoonView,
                    meta: { title: 'Products', permission: 'manage_products' },
                },
                {
                    path: 'customers',
                    name: 'customers',
                    component: ComingSoonView,
                    meta: { title: 'Customers', permission: 'manage_customers' },
                },
                {
                    path: 'debts',
                    name: 'debts',
                    component: ComingSoonView,
                    meta: { title: 'Debts', permission: 'record_debt_payments' },
                },
                {
                    path: 'sales-history',
                    name: 'sales-history',
                    component: ComingSoonView,
                    meta: { title: 'Sales History', permission: ['view_sales', 'view_reports'] },
                },
            ],
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'dashboard' },
        },
    ],
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (to.name === 'staff' && !auth.isAdmin) {
        return { name: 'dashboard' };
    }

    if (to.name === 'branches' && !auth.isAdmin) {
        return { name: 'dashboard' };
    }

    const permission = to.meta.permission as string | string[] | undefined;

    if (permission && !auth.canUse(permission)) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
