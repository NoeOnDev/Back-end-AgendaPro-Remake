import axios, { AxiosError, AxiosInstance, AxiosRequestConfig } from "axios";

const BASE_URL = import.meta.env.VITE_API_URL || "http://localhost:8000";

/**
 * Obtiene el token de autenticación almacenado en localStorage
 */
const getAuthToken = (): string | null => {
  return localStorage.getItem("authToken");
};

/**
 * Configura una instancia personalizada de Axios
 */
const createAxiosInstance = (): AxiosInstance => {
  const instance = axios.create({
    baseURL: BASE_URL,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    timeout: 10000, // 10 segundos
  });

  // Interceptor para solicitudes
  instance.interceptors.request.use(
    (config) => {
      const token = getAuthToken();

      // Si hay un token, lo añadimos al header de autorización
      if (token && config.headers) {
        config.headers["Authorization"] = `Bearer ${token}`;
      }

      return config;
    },
    (error) => {
      return Promise.reject(error);
    }
  );

  // Interceptor para respuestas
  instance.interceptors.response.use(
    (response) => {
      return response;
    },
    (error: AxiosError) => {
      // Manejo de errores específicos
      if (error.response) {
        // Si el error es 401 (no autorizado) y no estamos en la página de login
        if (
          error.response.status === 401 &&
          window.location.pathname !== "/sign-in"
        ) {
          // Eliminar el token
          localStorage.removeItem("authToken");

          // Redirigir a la página de login
          window.location.href = `/sign-in?callbackUrl=${encodeURIComponent(window.location.pathname)}`;
        }
      }

      return Promise.reject(error);
    }
  );

  return instance;
};

// Crea una instancia de Axios que podemos exportar
const api = createAxiosInstance();

/**
 * Interfaz para definir las respuestas de la API
 */
export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
}

/**
 * Funciones de servicio HTTP genéricas
 */
export const apiService = {
  get: async <T>(url: string, config?: AxiosRequestConfig): Promise<T> => {
    const response = await api.get<T>(url, config);
    return response.data;
  },

  post: async <T>(
    url: string,
    data?: any,
    config?: AxiosRequestConfig
  ): Promise<T> => {
    const response = await api.post<T>(url, data, config);
    return response.data;
  },

  put: async <T>(
    url: string,
    data?: any,
    config?: AxiosRequestConfig
  ): Promise<T> => {
    const response = await api.put<T>(url, data, config);
    return response.data;
  },

  delete: async <T>(url: string, config?: AxiosRequestConfig): Promise<T> => {
    const response = await api.delete<T>(url, config);
    return response.data;
  },
};

export default api;
