import { apiService, ApiResponse } from "./axios";
import { AxiosRequestConfig } from "axios";

/**
 * Clase base para servicios API
 * Puede ser extendida para crear servicios específicos
 */
export class BaseService<T> {
  protected basePath: string;

  constructor(basePath: string) {
    this.basePath = basePath;
  }

  /**
   * Obtiene todos los elementos
   */
  async getAll(config?: AxiosRequestConfig): Promise<ApiResponse<T[]>> {
    try {
      return await apiService.get<ApiResponse<T[]>>(this.basePath, config);
    } catch (error: any) {
      return {
        success: false,
        error:
          error.response?.data?.error ||
          error.message ||
          "Error al obtener datos",
      };
    }
  }

  /**
   * Obtiene un elemento por su ID
   */
  async getById(
    id: number | string,
    config?: AxiosRequestConfig
  ): Promise<ApiResponse<T>> {
    try {
      return await apiService.get<ApiResponse<T>>(
        `${this.basePath}/${id}`,
        config
      );
    } catch (error: any) {
      return {
        success: false,
        error:
          error.response?.data?.error ||
          error.message ||
          "Error al obtener los datos",
      };
    }
  }

  /**
   * Crea un nuevo elemento
   */
  async create(
    data: Partial<T>,
    config?: AxiosRequestConfig
  ): Promise<ApiResponse<T>> {
    try {
      return await apiService.post<ApiResponse<T>>(this.basePath, data, config);
    } catch (error: any) {
      return {
        success: false,
        error:
          error.response?.data?.error ||
          error.message ||
          "Error al crear los datos",
      };
    }
  }

  /**
   * Actualiza un elemento existente
   */
  async update(
    id: number | string,
    data: Partial<T>,
    config?: AxiosRequestConfig
  ): Promise<ApiResponse<T>> {
    try {
      return await apiService.put<ApiResponse<T>>(
        `${this.basePath}/${id}`,
        data,
        config
      );
    } catch (error: any) {
      return {
        success: false,
        error:
          error.response?.data?.error ||
          error.message ||
          "Error al actualizar los datos",
      };
    }
  }

  /**
   * Elimina un elemento
   */
  async delete(
    id: number | string,
    config?: AxiosRequestConfig
  ): Promise<ApiResponse<any>> {
    try {
      return await apiService.delete<ApiResponse<any>>(
        `${this.basePath}/${id}`,
        config
      );
    } catch (error: any) {
      return {
        success: false,
        error:
          error.response?.data?.error ||
          error.message ||
          "Error al eliminar los datos",
      };
    }
  }

  /**
   * Método personalizado GET
   */
  async customGet<R = any>(
    endpoint: string,
    config?: AxiosRequestConfig
  ): Promise<ApiResponse<R>> {
    try {
      return await apiService.get<ApiResponse<R>>(
        `${this.basePath}/${endpoint}`,
        config
      );
    } catch (error: any) {
      return {
        success: false,
        error:
          error.response?.data?.error ||
          error.message ||
          "Error en la petición",
      };
    }
  }

  /**
   * Método personalizado POST
   */
  async customPost<R = any, D = any>(
    endpoint: string,
    data?: D,
    config?: AxiosRequestConfig
  ): Promise<ApiResponse<R>> {
    try {
      return await apiService.post<ApiResponse<R>>(
        `${this.basePath}/${endpoint}`,
        data,
        config
      );
    } catch (error: any) {
      return {
        success: false,
        error:
          error.response?.data?.error ||
          error.message ||
          "Error en la petición",
      };
    }
  }
}
