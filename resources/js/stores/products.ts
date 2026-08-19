import { defineStore } from 'pinia';
import http from '../https';
import { extractErrorMessage } from './auth';
import type { Product, ProductResponse, ProductsResponse, ProductStatus, StockMovement, StockMovementType, StockMovementsResponse, StockResponse } from '../types';

export interface ProductForm {
    name: string;
    sku: string;
    category: string;
    unit: string;
    cost_price: string;
    selling_price: string;
    quantity: string;
    reorder_level: string;
}

export interface StockAdjustmentForm {
    type: Exclude<StockMovementType, 'sale'>;
    quantity: string;
    reorder_level: string;
    reason: string;
}

interface ProductState {
    products: Product[];
    stockMovements: StockMovement[];
    branch: ProductsResponse['branch'] | null;
    loading: boolean;
    error: string | null;
}

export const useProductStore = defineStore('products', {
    state: (): ProductState => ({
        products: [],
        stockMovements: [],
        branch: null,
        loading: false,
        error: null,
    }),

    getters: {
        activeProducts: (state) => state.products.filter((product) => product.status === 'active'),
        lowStockProducts: (state) => state.products.filter((product) => product.is_low_stock),
    },

    actions: {
        async fetchProducts(params: { search?: string; status?: ProductStatus; low_stock?: boolean } = {}) {
            return this.withLoading(async () => {
                const { data } = await http.get<ProductsResponse>('/products', { params });
                this.branch = data.branch;
                this.products = data.products;
            });
        },

        async fetchStockMovements(params: { type?: StockMovementType } = {}) {
            return this.withLoading(async () => {
                const { data } = await http.get<StockMovementsResponse>('/stock-movements', { params });
                this.branch = data.branch;
                this.stockMovements = data.movements;
            });
        },

        async createProduct(form: ProductForm) {
            return this.withLoading(async () => {
                const { data } = await http.post<ProductResponse>('/products', productPayload(form));
                this.products.push(data.product);
                this.sortProducts();
            });
        },

        async updateProduct(productId: string, form: ProductForm) {
            return this.withLoading(async () => {
                const { data } = await http.put<ProductResponse>(`/products/${productId}`, productPayload(form, false));
                this.replaceProduct(data.product);
            });
        },

        async archiveProduct(productId: string) {
            return this.withLoading(async () => {
                const { data } = await http.patch<ProductResponse>(`/products/${productId}/archive`);
                this.replaceProduct(data.product);
            });
        },

        async adjustStock(productId: string, form: StockAdjustmentForm) {
            return this.withLoading(async () => {
                const { data } = await http.patch<StockResponse>(`/products/${productId}/stock`, {
                    type: form.type,
                    quantity: Number(form.quantity),
                    reorder_level: form.reorder_level === '' ? null : Number(form.reorder_level),
                    reason: form.reason || null,
                });

                this.replaceProduct(data.product);
                await this.fetchStockMovements();
            });
        },

        replaceProduct(product: Product) {
            this.products = this.products.map((item) => item.id === product.id ? product : item);
            this.sortProducts();
        },

        sortProducts() {
            this.products = [...this.products].sort((a, b) => a.name.localeCompare(b.name));
        },

        async withLoading(callback: () => Promise<void>): Promise<boolean> {
            this.loading = true;
            this.error = null;

            try {
                await callback();
                return true;
            } catch (error) {
                this.error = extractErrorMessage(error);
                return false;
            } finally {
                this.loading = false;
            }
        },
    },
});

function productPayload(form: ProductForm, includeStock = true): Record<string, string | number | null> {
    const payload: Record<string, string | number | null> = {
        name: form.name,
        sku: form.sku || null,
        category: form.category || null,
        unit: form.unit || 'piece',
        cost_price: Number(form.cost_price || 0),
        selling_price: Number(form.selling_price || 0),
    };

    if (includeStock) {
        payload.quantity = Number(form.quantity || 0);
        payload.reorder_level = Number(form.reorder_level || 0);
    }

    return payload;
}
