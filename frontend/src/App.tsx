import { Switch, Route, Router as WouterRouter } from "wouter";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { ThemeProvider } from "@/components/theme-provider";
import { AuthGuard } from "@/components/auth-guard";

// Layouts
import { PublicLayout } from "@/components/layout/public-layout";

// Public pages
import HomePage from "@/pages/home";
import SearchPage from "@/pages/search";
import SchoolDetailPage from "@/pages/school-detail";
import LocalityDetailPage from "@/pages/locality-detail";
import AboutPage from "@/pages/about";
import DrivingRulesPage from "@/pages/driving-rules";
import ContactPage from "@/pages/contact";
import LoginPage from "@/pages/login";
import RegisterPage from "@/pages/register";

// Dashboard pages
import DashboardHomePage from "@/pages/dashboard/index";
import LeadsPage from "@/pages/dashboard/leads";
import ProfilePage from "@/pages/dashboard/profile";
import PackagesPage from "@/pages/dashboard/packages";
import DashboardReviewsPage from "@/pages/dashboard/reviews";
import AnalyticsPage from "@/pages/dashboard/analytics";

// Admin pages
import AdminHomePage from "@/pages/admin/index";
import AdminSchoolsPage from "@/pages/admin/schools";
import AdminReviewsPage from "@/pages/admin/reviews";
import AdminLocalitiesPage from "@/pages/admin/localities";
import AdminUsersPage from "@/pages/admin/users";

// Compare page
import ComparePage from "@/pages/compare";

// Not found
import NotFound from "@/pages/not-found";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30000,
    },
  },
});

function Router() {
  return (
    <Switch>
      {/* Public routes */}
      <Route path="/" component={HomePage} />
      <Route path="/search" component={SearchPage} />
      <Route path="/school/:slug" component={SchoolDetailPage} />
      <Route path="/locality/:slug" component={LocalityDetailPage} />
      <Route path="/about" component={AboutPage} />
      <Route path="/driving-rules" component={DrivingRulesPage} />
      <Route path="/contact" component={ContactPage} />
      <Route path="/auth/login" component={LoginPage} />
      <Route path="/auth/register" component={RegisterPage} />
      <Route path="/compare" component={ComparePage} />

      {/* Protected dashboard routes */}
      <Route path="/dashboard">
        {() => (
          <AuthGuard requireRole="school">
            <DashboardHomePage />
          </AuthGuard>
        )}
      </Route>
      <Route path="/dashboard/leads">
        {() => (
          <AuthGuard requireRole="school">
            <LeadsPage />
          </AuthGuard>
        )}
      </Route>
      <Route path="/dashboard/profile">
        {() => (
          <AuthGuard requireRole="school">
            <ProfilePage />
          </AuthGuard>
        )}
      </Route>
      <Route path="/dashboard/packages">
        {() => (
          <AuthGuard requireRole="school">
            <PackagesPage />
          </AuthGuard>
        )}
      </Route>
      <Route path="/dashboard/reviews">
        {() => (
          <AuthGuard requireRole="school">
            <DashboardReviewsPage />
          </AuthGuard>
        )}
      </Route>
      <Route path="/dashboard/analytics">
        {() => (
          <AuthGuard requireRole="school">
            <AnalyticsPage />
          </AuthGuard>
        )}
      </Route>

      {/* Protected admin routes */}
      <Route path="/admin">
        {() => (
          <AuthGuard requireRole="admin">
            <AdminHomePage />
          </AuthGuard>
        )}
      </Route>
      <Route path="/admin/schools">
        {() => (
          <AuthGuard requireRole="admin">
            <AdminSchoolsPage />
          </AuthGuard>
        )}
      </Route>
      <Route path="/admin/reviews">
        {() => (
          <AuthGuard requireRole="admin">
            <AdminReviewsPage />
          </AuthGuard>
        )}
      </Route>
      <Route path="/admin/localities">
        {() => (
          <AuthGuard requireRole="admin">
            <AdminLocalitiesPage />
          </AuthGuard>
        )}
      </Route>
      <Route path="/admin/users">
        {() => (
          <AuthGuard requireRole="admin">
            <AdminUsersPage />
          </AuthGuard>
        )}
      </Route>

      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <TooltipProvider>
          <WouterRouter base={import.meta.env.BASE_URL.replace(/\/$/, "")}>
            <Router />
          </WouterRouter>
          <Toaster />
        </TooltipProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}

export default App;
