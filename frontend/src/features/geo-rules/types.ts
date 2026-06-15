export interface GeoData {
  countries: Country[];
}

export interface Country {
  code: string;
  name: string;
  states: State[];
}

export interface State {
  code: string;
  name: string;
  cities: City[];
  regulations: StateRegulations;
}

export interface City {
  slug: string;
  name: string;
  areas: Area[];
  rtoOffices: RTOOffice[];
}

export interface Area {
  slug: string;
  name: string;
  nearestRtoSlug: string;
}

export interface RTOOffice {
  slug: string;
  name: string;
  address: string;
  phone?: string;
  timings?: string;
  services: string[];
  coordinates?: { lat: number; lng: number };
  googleMapsUrl?: string;
}

export interface StateRegulations {
  minimumAge: AgeRule[];
  documentsRequired: DocumentSet[];
  licenseProcess: LicenseProcessStep[];
  fees: FeeItem[];
  governmentLinks: GovernmentLink[];
  tips: string[];
}

export interface AgeRule {
  vehicleType: string;
  minimumAge: number;
  withGearless?: number;
  notes?: string;
}

export interface DocumentSet {
  licenseType: string;
  documents: string[];
}

export interface LicenseProcessStep {
  step: number;
  title: string;
  description: string;
  duration?: string;
}

export interface FeeItem {
  item: string;
  amount: string;
  notes?: string;
}

export interface GovernmentLink {
  label: string;
  url: string;
  description?: string;
  category: "rto" | "sarathi" | "parivahan" | "state" | "other";
}
