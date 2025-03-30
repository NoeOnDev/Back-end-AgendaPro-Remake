import { apiService } from './api/axios';

/**
 * Servicio de autenticación para conectar con la API empresarial
 */
interface AuthResult {
    success: boolean;
    user?: {
        id?: number;
        name?: string;
        email?: string;
        image?: string;
        role?: string;
        email_verified_at?: string;
        [key: string]: any;
    };
    token?: string;
    error?: string;
}

/**
 * Guarda el token de autenticación
 */
const saveToken = (token: string) => {
    localStorage.setItem('authToken', token);
};

/**
 * Obtiene el token de autenticación
 */
const getToken = (): string | null => {
    return localStorage.getItem('authToken');
};

/**
 * Elimina el token de autenticación
 */
const removeToken = () => {
    localStorage.removeItem('authToken');
};

/**
 * Inicia sesión con credenciales usando la API empresarial
 */
export async function signInWithCredentials(
    email: string,
    password: string
): Promise<AuthResult> {
    try {
        const data = await apiService.post<AuthResult>('/login', { email, password });

        if (data.success && data.token) {
            // Guardar token en localStorage para futuras peticiones
            saveToken(data.token);
        }

        return data;
    } catch (error: any) {
        console.error('Error al iniciar sesión:', error);
        return {
            success: false,
            error: error.response?.data?.error || error.message || 'Error al iniciar sesión'
        };
    }
}

/**
 * Cierra la sesión del usuario
 */
export async function signOut(): Promise<{ success: boolean; error?: string }> {
    try {
        const token = getToken();

        if (!token) {
            // Si no hay token, simplemente indicamos éxito
            removeToken();
            return { success: true };
        }

        const data = await apiService.post<{ success: boolean, error?: string }>('/logout');

        // Eliminar el token independientemente del resultado
        removeToken();

        return data;
    } catch (error: any) {
        console.error('Error al cerrar sesión:', error);
        // Eliminar el token aunque haya error
        removeToken();
        return {
            success: false,
            error: error.response?.data?.error || error.message || 'Error al cerrar sesión'
        };
    }
}

/**
 * Comprueba si hay una sesión activa
 */
export async function getCurrentUser(): Promise<AuthResult> {
    try {
        if (!getToken()) {
            return {
                success: false,
                error: 'No hay sesión activa'
            };
        }

        const data = await apiService.get<AuthResult>('/me');

        if (data.success && data.user) {
            return data;
        }

        // Si el token no es válido, lo eliminamos
        removeToken();

        return {
            success: false,
            error: data.error || 'Sesión inválida'
        };
    } catch (error: any) {
        console.error('Error al verificar sesión:', error);

        // Si el error es de autorización, eliminamos el token
        if (error.response?.status === 401) {
            removeToken();
        }

        return {
            success: false,
            error: error.response?.data?.error || error.message || 'Error al verificar sesión'
        };
    }
}

/**
 * Verifica si el email del usuario está verificado
 */
export function isEmailVerified(user: AuthResult['user']): boolean {
    return !!user?.email_verified_at;
}