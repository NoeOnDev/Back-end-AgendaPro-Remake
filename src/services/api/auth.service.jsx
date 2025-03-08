import apiClient from "./axios";

export const AuthService = {
  async login(emailOrUsername, password) {
    try {
      const response = await apiClient.post("/auth/login", {
        emailOrUsername,
        password,
      });
      return response.data;
    } catch (error) {
      throw this.handleError(error);
    }
  },

  handleError(error) {
    if (error.response) {
      return new Error(
        error.response.data.message || "Error en la autenticación"
      );
    }
    if (error.request) {
      return new Error("No se pudo conectar con el servidor");
    }
    return new Error("Error al procesar la solicitud");
  },
};
