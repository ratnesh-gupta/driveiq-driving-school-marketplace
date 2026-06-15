import type { GeoData, Country, State, City, Area, RTOOffice, StateRegulations } from "../types";
import geoData from "./india.json";

const data = geoData as GeoData;

export function getCountries(): Country[] {
  return data.countries;
}

export function getCountry(code: string): Country | undefined {
  return data.countries.find((c) => c.code === code);
}

export function getStatesByCountry(countryCode: string): State[] {
  return getCountry(countryCode)?.states ?? [];
}

export function getState(countryCode: string, stateCode: string): State | undefined {
  return getStatesByCountry(countryCode).find((s) => s.code === stateCode);
}

export function getCitiesByState(stateCode: string, countryCode = "IN"): City[] {
  return getState(countryCode, stateCode)?.cities ?? [];
}

export function getCity(citySlug: string, stateCode = "MH", countryCode = "IN"): City | undefined {
  return getCitiesByState(stateCode, countryCode).find((c) => c.slug === citySlug);
}

export function getAreasByCity(citySlug: string, stateCode = "MH"): Area[] {
  return getCity(citySlug, stateCode)?.areas ?? [];
}

export function getRTOOffices(citySlug: string, stateCode = "MH"): RTOOffice[] {
  return getCity(citySlug, stateCode)?.rtoOffices ?? [];
}

export function getRTOBySlug(citySlug: string, rtoSlug: string, stateCode = "MH"): RTOOffice | undefined {
  return getRTOOffices(citySlug, stateCode).find((r) => r.slug === rtoSlug);
}

export function getRTOForArea(citySlug: string, areaSlug: string, stateCode = "MH"): RTOOffice | null {
  const area = getAreasByCity(citySlug, stateCode).find((a) => a.slug === areaSlug);
  if (!area) return null;
  return getRTOBySlug(citySlug, area.nearestRtoSlug, stateCode) ?? null;
}

export function getRegulationsForState(stateCode: string, countryCode = "IN"): StateRegulations | null {
  return getState(countryCode, stateCode)?.regulations ?? null;
}
