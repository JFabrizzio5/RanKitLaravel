<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

interface UserItem {
    id: number
    name: string
    email: string
    role: string
    created_at: string
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface PaginatedUsers {
    data: UserItem[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    links: PaginationLink[]
}

const props = defineProps<{
    users: PaginatedUsers
    search: string
}>()

// --- THEME ---
const isDark = ref(true)

// --- SEARCH ---
const searchQuery = ref(props.search ?? '')
function doSearch() {
    router.get(route('admin.users.index'), { search: searchQuery.value }, { preserveState: true, replace: true })
}

// --- CREATE ADMIN MODAL ---
const showCreateModal = ref(false)
const formCreate = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

function submitCreate() {
    formCreate.post(route('admin.users.store'), {
        onSuccess: () => {
            formCreate.reset()
            showCreateModal.value = false
        },
    })
}

// --- ROLE TOGGLE ---
const loadingRole = ref<number | null>(null)

function toggleRole(user: UserItem) {
    const newRole = user.role === 'admin' ? 'user' : 'admin'
    loadingRole.value = user.id
    router.put(
        route('admin.users.role', { user: user.id }),
        { role: newRole },
        {
            preserveScroll: true,
            onFinish: () => { loadingRole.value = null },
        }
    )
}

function roleBadgeClass(role: string) {
    if (role === 'superadmin') return 'bg-purple-500/20 text-purple-400 border border-purple-500/30'
    if (role === 'admin') return 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30'
    return 'bg-gray-500/20 text-gray-400 border border-gray-500/30'
}

function roleLabel(role: string) {
    if (role === 'superadmin') return 'Superadmin'
    if (role === 'admin') return 'Admin'
    return 'Usuario'
}

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: '2-digit' })
}
</script>

<template>
    <Head title="Gestión de Usuarios" />

    <div class="min-h-screen bg-gray-50 dark:bg-[#050505] text-black dark:text-white font-sans">

        <!-- Navbar -->
        <nav class="fixed z-50 flex items-center justify-between w-full h-16 px-6 border-b lg:px-12 backdrop-blur-md bg-white/90 border-gray-200 dark:bg-[#050505]/95 dark:border-white/10">
            <div class="flex items-center gap-4">
                <Link :href="route('jangel.indexdos')" class="flex items-center gap-2 text-gray-500 hover:text-black dark:hover:text-white transition text-xs font-bold uppercase">
                    <i class="ph ph-arrow-left text-lg"></i>
                    Panel Torneos
                </Link>
                <span class="text-gray-300 dark:text-gray-700">/</span>
                <span class="text-xs font-bold uppercase tracking-widest text-yellow-500">Gestión de Usuarios</span>
            </div>
            <Link :href="route('logout')" method="post" as="button"
                class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-red-500 uppercase border rounded-lg border-red-500/20 hover:bg-red-500 hover:text-white transition">
                <i class="ph-bold ph-sign-out text-lg"></i>
                <span class="hidden sm:inline">Salir</span>
            </Link>
        </nav>

        <main class="max-w-6xl mx-auto px-6 py-8 pt-28">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-bold uppercase tracking-tight">Usuarios Registrados</h1>
                    <p class="text-xs text-gray-500 font-bold uppercase mt-1">{{ users.total }} usuarios en total</p>
                </div>
                <button
                    @click="showCreateModal = true"
                    class="flex items-center gap-2 px-5 py-2.5 bg-yellow-500 hover:bg-yellow-400 text-black text-xs font-bold uppercase rounded transition">
                    <i class="ph-bold ph-user-plus text-base"></i>
                    Crear Admin
                </button>
            </div>

            <!-- Search -->
            <div class="relative mb-5 max-w-sm">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input
                    v-model="searchQuery"
                    @keydown.enter="doSearch"
                    type="text"
                    placeholder="Buscar por nombre o email…"
                    class="w-full pl-9 pr-4 py-2 text-sm bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded outline-none focus:border-yellow-500 transition text-black dark:text-white"
                />
                <button @click="doSearch" class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold uppercase text-yellow-500 hover:text-yellow-400">
                    Buscar
                </button>
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden bg-white dark:bg-[#0a0a0a]">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                            <th class="text-left px-4 py-3 text-[10px] font-bold uppercase text-gray-500 tracking-widest">Nombre</th>
                            <th class="text-left px-4 py-3 text-[10px] font-bold uppercase text-gray-500 tracking-widest">Email</th>
                            <th class="text-left px-4 py-3 text-[10px] font-bold uppercase text-gray-500 tracking-widest">Rol</th>
                            <th class="text-left px-4 py-3 text-[10px] font-bold uppercase text-gray-500 tracking-widest">Registro</th>
                            <th class="text-right px-4 py-3 text-[10px] font-bold uppercase text-gray-500 tracking-widest">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="users.data.length === 0">
                            <td colspan="5" class="text-center py-12 text-gray-400 text-xs font-bold uppercase">
                                No se encontraron usuarios.
                            </td>
                        </tr>
                        <tr
                            v-for="u in users.data"
                            :key="u.id"
                            class="border-b border-gray-100 dark:border-white/5 last:border-0 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                            <td class="px-4 py-3 font-medium">{{ u.name }}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ u.email }}</td>
                            <td class="px-4 py-3">
                                <span :class="roleBadgeClass(u.role)" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                    {{ roleLabel(u.role) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(u.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="u.role !== 'superadmin'"
                                    @click="toggleRole(u)"
                                    :disabled="loadingRole === u.id"
                                    :class="u.role === 'admin'
                                        ? 'border-red-500/40 text-red-400 hover:bg-red-500 hover:text-white hover:border-red-500'
                                        : 'border-yellow-500/40 text-yellow-500 hover:bg-yellow-500 hover:text-black hover:border-yellow-500'"
                                    class="px-3 py-1.5 text-[10px] font-bold uppercase border rounded transition disabled:opacity-40 disabled:cursor-not-allowed">
                                    <span v-if="loadingRole === u.id">
                                        <i class="ph ph-spinner animate-spin"></i>
                                    </span>
                                    <span v-else>
                                        {{ u.role === 'admin' ? 'Quitar Admin' : 'Hacer Admin' }}
                                    </span>
                                </button>
                                <span v-else class="text-[10px] text-gray-500 uppercase font-bold">Superadmin</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="users.last_page > 1" class="flex items-center justify-center gap-1 mt-6">
                <template v-for="link in users.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        :class="link.active
                            ? 'bg-yellow-500 text-black border-yellow-500'
                            : 'bg-white dark:bg-white/5 text-gray-500 border-gray-300 dark:border-white/10 hover:border-yellow-500 hover:text-yellow-500'"
                        class="px-3 py-1.5 text-[10px] font-bold border rounded transition min-w-[36px] text-center"
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1.5 text-[10px] font-bold text-gray-300 dark:text-gray-700 min-w-[36px] text-center"
                    />
                </template>
            </div>

        </main>

        <!-- CREATE ADMIN MODAL -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
                <div class="w-full max-w-md bg-white dark:bg-[#101012] rounded-xl border border-gray-200 dark:border-white/10 shadow-2xl">

                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-white/10">
                        <h2 class="text-sm font-bold uppercase tracking-widest flex items-center gap-2">
                            <i class="ph-bold ph-user-plus text-yellow-500 text-base"></i>
                            Crear Cuenta Admin
                        </h2>
                        <button @click="showCreateModal = false" class="text-gray-400 hover:text-red-500 transition">
                            <i class="ph ph-x text-xl"></i>
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="p-6 space-y-4">

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Nombre</label>
                            <input
                                v-model="formCreate.name"
                                type="text"
                                placeholder="Nombre completo"
                                class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded outline-none focus:border-yellow-500 transition text-black dark:text-white"
                                required
                            />
                            <p v-if="formCreate.errors.name" class="text-red-500 text-[10px] mt-1">{{ formCreate.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Correo electrónico</label>
                            <input
                                v-model="formCreate.email"
                                type="email"
                                placeholder="admin@ejemplo.com"
                                class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded outline-none focus:border-yellow-500 transition text-black dark:text-white"
                                required
                            />
                            <p v-if="formCreate.errors.email" class="text-red-500 text-[10px] mt-1">{{ formCreate.errors.email }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Contraseña</label>
                            <input
                                v-model="formCreate.password"
                                type="password"
                                placeholder="Mínimo 8 caracteres"
                                class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded outline-none focus:border-yellow-500 transition text-black dark:text-white"
                                required
                            />
                            <p v-if="formCreate.errors.password" class="text-red-500 text-[10px] mt-1">{{ formCreate.errors.password }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Confirmar Contraseña</label>
                            <input
                                v-model="formCreate.password_confirmation"
                                type="password"
                                placeholder="Repite la contraseña"
                                class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded outline-none focus:border-yellow-500 transition text-black dark:text-white"
                                required
                            />
                        </div>

                        <div class="pt-2 flex gap-3">
                            <button
                                type="button"
                                @click="showCreateModal = false"
                                class="flex-1 py-2.5 text-xs font-bold uppercase border border-gray-300 dark:border-white/10 rounded hover:border-gray-500 transition text-gray-500">
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="formCreate.processing"
                                class="flex-1 py-2.5 text-xs font-bold uppercase bg-yellow-500 hover:bg-yellow-400 text-black rounded transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <span v-if="formCreate.processing">Creando…</span>
                                <span v-else>Crear Admin</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </Teleport>

    </div>
</template>
