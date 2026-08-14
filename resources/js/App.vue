<script setup>
import { onMounted, ref } from 'vue'
import api, { clearToken, hasToken, saveToken } from './lib/api'

const loginForm = ref({ email: 'demo@example.com', password: 'password' })
const loginLoading = ref(false)
const isAuthenticated = ref(hasToken())
const currentUser = ref(null)
const balance = ref('0.00')
const code = ref('')
const claimLoading = ref(false)
const notification = ref(null)
const history = ref([])
const historyLoading = ref(false)
const statusFilter = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const revokingId = ref(null)

const statusOptions = [
    { value: '', label: 'Усі записи' },
    { value: 'applied', label: 'Застосовані' },
    { value: 'rejected', label: 'Відхилені' },
    { value: 'revoked', label: 'Скасовані' },
]

function showNotification(type, text) {
    notification.value = { type, text }
}

function errorMessage(error, fallback = 'Сталася непередбачена помилка.') {
    const response = error?.response?.data

    if (response?.errors) {
        return Object.values(response.errors).flat()[0]
    }

    return response?.message || fallback
}

async function login() {
    loginLoading.value = true
    notification.value = null

    try {
        const response = await api.post('/api/auth/login', loginForm.value)
        saveToken(response.data.token)
        isAuthenticated.value = true
        currentUser.value = response.data.data
        balance.value = response.data.data.balance
        await loadHistory()
        showNotification('success', 'Вхід виконано. Можна застосувати промокод.')
    } catch (error) {
        showNotification('error', errorMessage(error, 'Не вдалося виконати вхід.'))
    } finally {
        loginLoading.value = false
    }
}

async function loadSession() {
    try {
        const response = await api.get('/api/me')
        currentUser.value = response.data.data
        balance.value = response.data.data.balance
        await loadHistory()
    } catch {
        clearToken()
        isAuthenticated.value = false
    }
}

async function loadHistory(page = 1) {
    historyLoading.value = true

    try {
        const response = await api.get('/api/promo/history', {
            params: {
                page,
                ...(statusFilter.value ? { status: statusFilter.value } : {}),
            },
        })
        history.value = response.data.data
        meta.value = response.data.meta
    } catch (error) {
        showNotification('error', errorMessage(error, 'Не вдалося завантажити історію.'))
    } finally {
        historyLoading.value = false
    }
}

async function claimPromo() {
    claimLoading.value = true
    notification.value = null

    try {
        const response = await api.post('/api/promo/claim', { code: code.value })
        balance.value = response.data.data.balance
        code.value = ''
        showNotification('success', `${response.data.message} Бонус: ${response.data.data.bonus}`)
        await loadHistory()
    } catch (error) {
        showNotification('error', errorMessage(error, 'Не вдалося застосувати промокод.'))
        await loadHistory()
    } finally {
        claimLoading.value = false
    }
}

async function revokeClaim(claim) {
    if (!window.confirm(`Скасувати бонус за промокодом ${claim.code}? Кошти буде списано з балансу.`)) {
        return
    }

    revokingId.value = claim.id
    notification.value = null

    try {
        const response = await api.patch(`/api/promo/${claim.id}/revoke`)
        balance.value = response.data.data.balance
        showNotification('success', response.data.message)
        await loadHistory(meta.value.current_page)
    } catch (error) {
        showNotification('error', errorMessage(error, 'Не вдалося скасувати бонус.'))
    } finally {
        revokingId.value = null
    }
}

async function logout() {
    try {
        await api.delete('/api/auth/logout')
    } finally {
        clearToken()
        isAuthenticated.value = false
        currentUser.value = null
        history.value = []
        notification.value = null
    }
}

function statusLabel(status) {
    return {
        applied: 'Застосовано',
        rejected: 'Відхилено',
        revoked: 'Скасовано',
    }[status] || status
}

function dateLabel(value) {
    if (!value) return '—'

    return new Intl.DateTimeFormat('uk-UA', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

function changeFilter() {
    loadHistory()
}

onMounted(() => {
    if (isAuthenticated.value) {
        loadSession()
    }
})
</script>

<template>
    <main class="page-shell">
        <section class="hero">
            <div>
                <p class="eyebrow">PROMO WALLET</p>
                <h1>Бонуси без сюрпризів</h1>
                <p class="hero-copy">Застосовуйте промокоди, відстежуйте кожну операцію та безпечно скасовуйте помилкові нарахування.</p>
            </div>
            <div class="hero-mark" aria-hidden="true">₴</div>
        </section>

        <section v-if="!isAuthenticated" class="card login-card">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">ДЕМО-ДОСТУП</p>
                    <h2>Увійдіть як гравець</h2>
                </div>
                <span class="pill neutral">Sanctum token</span>
            </div>
            <form class="login-form" @submit.prevent="login">
                <label>
                    Email
                    <input v-model="loginForm.email" type="email" autocomplete="username" required>
                </label>
                <label>
                    Пароль
                    <input v-model="loginForm.password" type="password" autocomplete="current-password" required>
                </label>
                <button class="primary-button" :disabled="loginLoading">
                    {{ loginLoading ? 'Входимо…' : 'Увійти' }}
                </button>
            </form>
            <p class="hint">Для локальної демонстрації: <code>demo@example.com</code> / <code>password</code></p>
        </section>

        <template v-else>
            <section class="balance-card">
                <div>
                    <p class="eyebrow">ПОТОЧНИЙ БАЛАНС</p>
                    <p class="balance">₴ {{ balance }}</p>
                    <p class="muted">{{ currentUser?.email }}</p>
                </div>
                <button class="text-button" @click="logout">Вийти</button>
            </section>

            <section class="card claim-card">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">ТІКЕТ 1</p>
                        <h2>Нарахувати бонус</h2>
                    </div>
                    <span class="pill accent">6–12 символів</span>
                </div>
                <form class="claim-form" @submit.prevent="claimPromo">
                    <label class="grow">
                        Промокод
                        <input v-model="code" type="text" minlength="6" maxlength="12" pattern="[A-Za-z0-9]{6,12}" placeholder="Наприклад, WELCOME10" required>
                    </label>
                    <button class="primary-button" :disabled="claimLoading">
                        {{ claimLoading ? 'Перевіряємо…' : 'Застосувати' }}
                    </button>
                </form>
                <div v-if="notification" class="notification" :class="notification.type" role="status">
                    {{ notification.text }}
                </div>
            </section>

            <section class="card history-card">
                <div class="section-heading history-heading">
                    <div>
                        <p class="eyebrow">ІСТОРІЯ ОПЕРАЦІЙ</p>
                        <h2>Промокоди</h2>
                    </div>
                    <label class="filter-label">
                        <span class="sr-only">Фільтр за статусом</span>
                        <select v-model="statusFilter" @change="changeFilter">
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>
                </div>

                <div v-if="historyLoading" class="empty-state">Завантажуємо історію…</div>
                <div v-else-if="!history.length" class="empty-state">Історія поки порожня. Застосуйте свій перший промокод.</div>
                <div v-else class="history-list">
                    <article v-for="claim in history" :key="claim.id" class="history-row">
                        <div class="history-main">
                            <div class="code-name">{{ claim.code }}</div>
                            <div class="history-date">{{ dateLabel(claim.claimed_at) }}</div>
                        </div>
                        <div class="history-amount">₴ {{ claim.amount }}</div>
                        <div class="history-status">
                            <span class="pill" :class="claim.status">{{ statusLabel(claim.status) }}</span>
                            <small v-if="claim.reason" class="reason">{{ claim.reason }}</small>
                        </div>
                        <button v-if="claim.status === 'applied'" class="revoke-button" :disabled="revokingId === claim.id" @click="revokeClaim(claim)">
                            {{ revokingId === claim.id ? 'Скасовуємо…' : 'Скасувати' }}
                        </button>
                    </article>
                </div>

                <div v-if="meta.last_page > 1" class="pagination">
                    <button :disabled="meta.current_page === 1" @click="loadHistory(meta.current_page - 1)">← Назад</button>
                    <span>Сторінка {{ meta.current_page }} з {{ meta.last_page }}</span>
                    <button :disabled="meta.current_page === meta.last_page" @click="loadHistory(meta.current_page + 1)">Далі →</button>
                </div>
            </section>
        </template>
    </main>
</template>
