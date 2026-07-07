import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:6001'

const apiClient = axios.create({
  baseURL: API_BASE,
  withCredentials: true,
  withXSRFToken: true,
})

apiClient.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      const redirect = encodeURIComponent(window.location.href)
      window.location.href = `${API_BASE}/api/auth/login?redirect=${redirect}`
    }
    return Promise.reject(err)
  }
)

export default apiClient
