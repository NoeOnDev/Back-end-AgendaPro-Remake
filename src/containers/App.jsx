import { BrowserRouter, Routes, Route } from "react-router-dom";
import { LoginPage } from "../pages/LoginPage";
import { DashboardLayoutNavigationLinks } from "./Dashboard";

export function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<LoginPage />} />
        <Route
          path="/dashboard/*"
          element={<DashboardLayoutNavigationLinks />}
        />
      </Routes>
    </BrowserRouter>
  );
}
