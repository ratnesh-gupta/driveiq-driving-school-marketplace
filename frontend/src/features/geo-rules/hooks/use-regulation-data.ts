import { useMemo } from "react";
import { useLocationStore } from "../stores/location-store";
import {
  getRegulationsForState,
  getRTOOffices,
  getRTOForArea,
} from "../data";
import type { StateRegulations, RTOOffice, GovernmentLink } from "../types";

interface RegulationData {
  regulations: StateRegulations | null;
  nearestRto: RTOOffice | null;
  allRtos: RTOOffice[];
  governmentLinks: GovernmentLink[];
}

export function useRegulationData(): RegulationData {
  const { selectedState, selectedCity, selectedArea } = useLocationStore();

  return useMemo(() => {
    const regulations = getRegulationsForState(selectedState);
    const allRtos = getRTOOffices(selectedCity, selectedState);
    const nearestRto = selectedArea
      ? getRTOForArea(selectedCity, selectedArea, selectedState)
      : null;
    const governmentLinks = regulations?.governmentLinks ?? [];

    return { regulations, nearestRto, allRtos, governmentLinks };
  }, [selectedState, selectedCity, selectedArea]);
}
