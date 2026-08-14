import axios from 'axios'

const api = axios.create({
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('promo_wallet_token')

    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    return config
})

export function saveToken(token) {
    localStorage.setItem('promo_wallet_token', token)
}

export function clearToken() {
    localStorage.removeItem('promo_wallet_token')
}

export function hasToken() {
    return Boolean(localStorage.getItem('promo_wallet_token'))
}

export default api
