import { Switch, Route, Router as WouterRouter } from "wouter";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/components/ui/toaster";
import { Toaster as SonnerToaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { ThemeProvider } from "@/components/theme-provider";
import { AuthGuard } from "@/components/auth-guard";
import { CookieConsent } from "@/components/legal/cookie-consent";

import HomePage from "@/pages/home";
import SearchPage from "@/pages/search";
import SchoolDetailPage from "@/pages/school-detail";
import LocalityDetailPage from "@/pages/locality-detail";
import AboutPage from "@/pages/about";
import DrivingRulesPage from "@/pages/driving-rules";
import ContactPage from "@/pages/contact";
import LoginPage from "@/pages/login";
import RegisterPage from "@/pages/register";
import PrivacyPage from "@/pages/privacy";
import TermsPage from "@/pages/terms";
import DataRequestPage from "@/pages/data-request";

import DashboardHomePage from "@/pages/dashboard/index";
import LeadsPage from "@/pages/dashboard/leads";
import ProfilePage from "@/pages/dashboard/profile";
import PackagesPage from "@/pages/dashboard/packages";
import DashboardReviewsPage from "@/pages/dashboard/reviews";
import AnalyticsPage from "@/pages/dashboard/analytics";
import LearnersPage from "@/pages/dashboard/learners";
import InstructorsPage from "@/pages/dashboard/instructors";
import SchedulesPage from "@/pages/dashboard/schedules";
import VehiclesPage from "@/pages/dashboard/vehicles";
import SchoolMessagesPage from "@/pages/dashboard/messages";
import PaymentsPage from "@/pages/dashboard/payments";

import AdminHomePage from "@/pages/admin/index";
import AdminSchoolsPage from "@/pages/admin/schools";
import AdminReviewsPage from "@/pages/admin/reviews";
import AdminLocalitiesPage from "@/pages/admin/localities";
import AdminUsersPage from "@/pages/admin/users";
import AdminAnalyticsPage from "@/pages/admin/analytics";
import AdminDataRequestsPage from "@/pages/admin/data-requests";

import InstructorHomePage from "@/pages/instructor/index";
import InstructorSessionsPage from "@/pages/instructor/sessions";
import InstructorMessagesPage from "@/pages/instructor/messages";

import LearnerHomePage from "@/pages/learner/index";
import LearnerProgressPage from "@/pages/learner/progress";
import LearnerSessionsPage from "@/pages/learner/sessions";
import LearnerMessagesPage from "@/pages/learner/messages";

import ComparePage from "@/pages/compare";
import NotFound from "@/pages/not-found";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30000,
    },
  },
});

function schoolGuard(node: React.ReactNode) {
  return <AuthGuard requireRole="school">{node}</AuthGuard>;
}

function Router() {
  return (
    <Switch>
      <Route path="/" component={HomePage} />
      <Route path="/search" component={SearchPage} />
      <Route path="/school/:slug" component={SchoolDetailPage} />
      <Route path="/locality/:slug" component={LocalityDetailPage} />
      <Route path="/about" component={AboutPage} />
      <Route path="/driving-rules" component={DrivingRulesPage} />
      <Route path="/contact" component={ContactPage} />
      <Route path="/privacy" component={PrivacyPage} />
      <Route path="/terms" component={TermsPage} />
      <Route path="/privacy/data-request" component={DataRequestPage} />
      <Route path="/auth/login" component={LoginPage} />
      <Route path="/auth/register" component={RegisterPage} />
      <Route path="/compare" component={ComparePage} />

      <Route path="/dashboard">{() => schoolGuard(<DashboardHomePage />)}</Route>
      <Route path="/dashboard/leads">{() => schoolGuard(<LeadsPage />)}</Route>
      <Route path="/dashboard/learners">{() => schoolGuard(<LearnersPage />)}</Route>
      <Route path="/dashboard/instructors">{() => schoolGuard(<InstructorsPage />)}</Route>
      <Route path="/dashboard/schedules">{() => schoolGuard(<SchedulesPage />)}</Route>
      <Route path="/dashboard/vehicles">{() => schoolGuard(<VehiclesPage />)}</Route>
      <Route path="/dashboard/payments">{() => schoolGuard(<PaymentsPage />)}</Route>
      <Route path="/dashboard/messages">{() => schoolGuard(<SchoolMessagesPage />)}</Route>
      <Route path="/dashboard/profile">{() => schoolGuard(<ProfilePage />)}</Route>
      <Route path="/dashboard/packages">{() => schoolGuard(<PackagesPage />)}</Route>
      <Route path="/dashboard/reviews">{() => schoolGuard(<DashboardReviewsPage />)}</Route>
      <Route path="/dashboard/analytics">{() => schoolGuard(<AnalyticsPage />)}</Route>

      <Route path="/admin">{() => <AuthGuard requireRole="admin"><AdminHomePage /></AuthGuard>}</Route>
      <Route path="/admin/schools">{() => <AuthGuard requireRole="admin"><AdminSchoolsPage /></AuthGuard>}</Route>
      <Route path="/admin/reviews">{() => <AuthGuard requireRole="admin"><AdminReviewsPage /></AuthGuard>}</Route>
      <Route path="/admin/localities">{() => <AuthGuard requireRole="admin"><AdminLocalitiesPage /></AuthGuard>}</Route>
      <Route path="/admin/users">{() => <AuthGuard requireRole="admin"><AdminUsersPage /></AuthGuard>}</Route>
      <Route path="/admin/analytics">{() => <AuthGuard requireRole="admin"><AdminAnalyticsPage /></AuthGuard>}</Route>
      <Route path="/admin/data-requests">{() => <AuthGuard requireRole="admin"><AdminDataRequestsPage /></AuthGuard>}</Route>

      <Route path="/instructor">{() => <AuthGuard requireRole="instructor"><InstructorHomePage /></AuthGuard>}</Route>
      <Route path="/instructor/sessions">{() => <AuthGuard requireRole="instructor"><InstructorSessionsPage /></AuthGuard>}</Route>
      <Route path="/instructor/messages">{() => <AuthGuard requireRole="instructor"><InstructorMessagesPage /></AuthGuard>}</Route>
      <Route path="/instructor/attendance">{() => <AuthGuard requireRole="instructor"><InstructorSessionsPage /></AuthGuard>}</Route>

      <Route path="/learner">{() => <AuthGuard requireRole="learner"><LearnerHomePage /></AuthGuard>}</Route>
      <Route path="/learner/progress">{() => <AuthGuard requireRole="learner"><LearnerProgressPage /></AuthGuard>}</Route>
      <Route path="/learner/sessions">{() => <AuthGuard requireRole="learner"><LearnerSessionsPage /></AuthGuard>}</Route>
      <Route path="/learner/documents">{() => <AuthGuard requireRole="learner"><LearnerHomePage /></AuthGuard>}</Route>
      <Route path="/learner/messages">{() => <AuthGuard requireRole="learner"><LearnerMessagesPage /></AuthGuard>}</Route>

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
          <CookieConsent />
          <Toaster />
          <SonnerToaster />
        </TooltipProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}

export default App;
