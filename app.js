/**
 * TaskFlow — Client-side Application
 *
 * Handles authentication, CRUD operations, filtering, and UI state.
 * Dependencies: none (vanilla JS, ES6+)
 */

// ── API Helper ───────────────────────────────────────────────
const API = {
    async request(url, options = {}) {
        const config = {
            headers: { 'Content-Type': 'application/json' },
            ...options,
        };
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }
        const res = await fetch(url, config);
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Something went wrong');
        return data;
    },

    // Auth
    login(email, password)            { return this.request('php/auth.php?action=login',    { method: 'POST', body: { email, password } }); },
    register(username, email, password){ return this.request('php/auth.php?action=register', { method: 'POST', body: { username, email, password } }); },
    me()                              { return this.request('php/auth.php?action=me'); },
    logout()                          { return this.request('php/auth.php?action=logout'); },

    // Tasks
    getTasks(params = {})  { const q = new URLSearchParams(params).toString(); return this.request(`php/tasks.php${q ? '?' + q : ''}`); },
    getTask(id)            { return this.request(`php/tasks.php?id=${id}`); },
    createTask(data)       { return this.request('php/tasks.php',          { method: 'POST', body: data }); },
    updateTask(id, data)   { return this.request(`php/tasks.php?id=${id}`, { method: 'PUT',  body: data }); },
    deleteTask(id)         { return this.request(`php/tasks.php?id=${id}`, { method: 'DELETE' }); },
    getStats()             { return this.request('php/tasks.php?action=stats'); },

    // Categories
    getCategories()       { return this.request('php/categories.php'); },
    createCategory(data)  { return this.request('php/categories.php', { method: 'POST', body: data }); },
    deleteCategory(id)    { return this.request(`php/categories.php?id=${id}`, { method: 'DELETE' }); },
};


// ── State ────────────────────────────────────────────────────
let currentUser = null;
let tasks       = [];
let categories  = [];
let filters     = { status: '', priority: '', category_id: '', search: '' };


// ── DOM References ───────────────────────────────────────────
const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const authScreen   = $('#auth-screen');
const appScreen    = $('#app-screen');
const toastContainer = $('#toast-container');


// ── Toast Notifications ──────────────────────────────────────
function toast(message, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    toastContainer.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}


// ── Auth ─────────────────────────────────────────────────────
function initAuth() {
    const tabs   = $$('.auth-tab');
    const loginForm    = $('#login-form');
    const registerForm = $('#register-form');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const target = tab.dataset.tab;
            loginForm.classList.toggle('hidden', target !== 'login');
            registerForm.classList.toggle('hidden', target !== 'register');
        });
    });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await API.login(
                $('#login-email').value,
                $('#login-password').value
            );
            currentUser = res.user;
            showApp();
        } catch (err) {
            toast(err.message, 'error');
        }
    });

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await API.register(
                $('#reg-username').value,
                $('#reg-email').value,
                $('#reg-password').value
            );
            currentUser = res.user;
            showApp();
        } catch (err) {
            toast(err.message, 'error');
        }
    });
}


// ── Show App ─────────────────────────────────────────────────
async function showApp() {
    authScreen.classList.add('hidden');
    appScreen.classList.remove('hidden');

    // Set user info in navbar
    $('#user-name').textContent = currentUser.username;
    const avatar = $('#user-avatar');
    avatar.textContent = currentUser.username[0].toUpperCase();
    avatar.style.background = currentUser.avatar_color;

    await Promise.all([loadCategories(), loadTasks(), loadStats()]);
}


// ── Load Data ────────────────────────────────────────────────
async function loadTasks() {
    try {
        const params = {};
        if (filters.status)      params.status      = filters.status;
        if (filters.priority)    params.priority     = filters.priority;
        if (filters.category_id) params.category_id  = filters.category_id;
        if (filters.search)      params.search       = filters.search;

        tasks = await API.getTasks(params);
        renderTasks();
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function loadCategories() {
    try {
        categories = await API.getCategories();
        renderSidebarCategories();
        renderCategorySelect();
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function loadStats() {
    try {
        const stats = await API.getStats();
        $('#stat-total').textContent      = stats.total;
        $('#stat-in-progress').textContent = stats.by_status.in_progress;
        $('#stat-done').textContent        = stats.by_status.done;
        $('#stat-overdue').textContent     = stats.overdue;
    } catch (err) {
        // Stats are non-critical
    }
}


// ── Render Tasks ─────────────────────────────────────────────
function renderTasks() {
    const container = $('#task-list');

    if (tasks.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3>No tasks found</h3>
                <p>Create your first task to get started!</p>
            </div>`;
        return;
    }

    container.innerHTML = tasks.map(task => {
        const isDone    = task.status === 'done';
        const isOverdue = task.due_date && !isDone && new Date(task.due_date) < new Date();
        const dueLabel  = task.due_date ? formatDate(task.due_date) : '';

        return `
        <div class="task-card priority-${task.priority} status-${task.status}" data-id="${task.id}">
            <div class="task-checkbox ${isDone ? 'checked' : ''}" onclick="toggleTask(event, ${task.id}, '${task.status}')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="task-body" onclick="openEditModal(${task.id})">
                <div class="task-title">${escapeHtml(task.title)}</div>
                ${task.description ? `<div class="task-desc">${escapeHtml(task.description)}</div>` : ''}
                <div class="task-meta">
                    ${task.category_name ? `<span class="tag tag-category"><span class="category-dot" style="background:${task.category_color}"></span>${escapeHtml(task.category_name)}</span>` : ''}
                    ${dueLabel ? `<span class="tag tag-due ${isOverdue ? 'overdue' : ''}">${isOverdue ? 'Overdue: ' : ''}${dueLabel}</span>` : ''}
                    <span class="tag tag-status">${formatStatus(task.status)}</span>
                </div>
            </div>
            <div class="task-actions">
                <button class="btn btn-ghost btn-icon btn-sm" onclick="openEditModal(${task.id})" title="Edit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="btn btn-ghost btn-icon btn-sm" onclick="confirmDelete(${task.id})" title="Delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
            </div>
        </div>`;
    }).join('');
}


// ── Render Sidebar Categories ────────────────────────────────
function renderSidebarCategories() {
    const list = $('#category-list');
    list.innerHTML = `
        <li>
            <button class="${!filters.category_id ? 'active' : ''}" onclick="filterByCategory('')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                All Tasks
                <span class="nav-badge">${tasks.length}</span>
            </button>
        </li>
        ${categories.map(cat => `
            <li>
                <button class="${filters.category_id == cat.id ? 'active' : ''}" onclick="filterByCategory(${cat.id})">
                    <span class="category-dot" style="background:${cat.color}"></span>
                    ${escapeHtml(cat.name)}
                    <span class="nav-badge">${cat.task_count}</span>
                </button>
            </li>
        `).join('')}
    `;
}


// ── Render Category <select> in modal ────────────────────────
function renderCategorySelect() {
    const selects = $$('.category-select');
    const opts = `<option value="">No Category</option>` +
        categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    selects.forEach(sel => sel.innerHTML = opts);
}


// ── Task Actions ─────────────────────────────────────────────
async function toggleTask(e, id, currentStatus) {
    e.stopPropagation();
    const newStatus = currentStatus === 'done' ? 'todo' : 'done';
    try {
        await API.updateTask(id, { status: newStatus });
        toast(newStatus === 'done' ? 'Task completed!' : 'Task reopened', 'success');
        await Promise.all([loadTasks(), loadStats()]);
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function confirmDelete(id) {
    if (!confirm('Delete this task? This cannot be undone.')) return;
    try {
        await API.deleteTask(id);
        toast('Task deleted', 'success');
        await Promise.all([loadTasks(), loadStats()]);
    } catch (err) {
        toast(err.message, 'error');
    }
}


// ── Modal ────────────────────────────────────────────────────
function openCreateModal() {
    $('#modal-title').textContent = 'New Task';
    $('#task-form').reset();
    $('#task-form').dataset.taskId = '';
    renderCategorySelect();
    $('#modal-overlay').classList.remove('hidden');
}

async function openEditModal(id) {
    try {
        const task = await API.getTask(id);
        $('#modal-title').textContent = 'Edit Task';

        const form = $('#task-form');
        form.dataset.taskId = id;
        form.querySelector('[name="title"]').value       = task.title;
        form.querySelector('[name="description"]').value = task.description || '';
        form.querySelector('[name="priority"]').value    = task.priority;
        form.querySelector('[name="status"]').value      = task.status;
        form.querySelector('[name="due_date"]').value    = task.due_date || '';

        renderCategorySelect();
        form.querySelector('[name="category_id"]').value = task.category_id || '';

        $('#modal-overlay').classList.remove('hidden');
    } catch (err) {
        toast(err.message, 'error');
    }
}

function closeModal() {
    $('#modal-overlay').classList.add('hidden');
}

async function handleTaskSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const id   = form.dataset.taskId;
    const data = {
        title:       form.querySelector('[name="title"]').value,
        description: form.querySelector('[name="description"]').value,
        priority:    form.querySelector('[name="priority"]').value,
        status:      form.querySelector('[name="status"]').value,
        category_id: form.querySelector('[name="category_id"]').value || null,
        due_date:    form.querySelector('[name="due_date"]').value || null,
    };

    try {
        if (id) {
            await API.updateTask(id, data);
            toast('Task updated!', 'success');
        } else {
            await API.createTask(data);
            toast('Task created!', 'success');
        }
        closeModal();
        await Promise.all([loadTasks(), loadStats(), loadCategories()]);
    } catch (err) {
        toast(err.message, 'error');
    }
}


// ── Filters ──────────────────────────────────────────────────
function filterByCategory(catId) {
    filters.category_id = catId;
    loadTasks();
    renderSidebarCategories();
}

function applyFilters() {
    filters.status   = $('#filter-status').value;
    filters.priority = $('#filter-priority').value;
    loadTasks();
}

let searchTimeout;
function handleSearch(value) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        filters.search = value;
        loadTasks();
    }, 300);
}


// ── Logout ───────────────────────────────────────────────────
async function handleLogout() {
    await API.logout();
    currentUser = null;
    appScreen.classList.add('hidden');
    authScreen.classList.remove('hidden');
}


// ── Helpers ──────────────────────────────────────────────────
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatStatus(status) {
    return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
}


// ── Initialization ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    initAuth();

    // Wire up modal
    $('#task-form').addEventListener('submit', handleTaskSubmit);
    $('#modal-overlay').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeModal();
    });

    // Wire up filters
    $('#filter-status').addEventListener('change', applyFilters);
    $('#filter-priority').addEventListener('change', applyFilters);
    $('#search-input').addEventListener('input', (e) => handleSearch(e.target.value));

    // Check if already logged in
    try {
        const res = await API.me();
        if (res.user) {
            currentUser = res.user;
            showApp();
        }
    } catch {
        // Not logged in, show auth screen
    }
});
