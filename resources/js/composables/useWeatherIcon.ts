import { Cloud, CloudDrizzle, CloudFog, CloudLightning, CloudRain, CloudSnow, CloudSun, Sun, type LucideIcon } from 'lucide-vue-next';

/** Map a WMO weather code to a label + lucide icon. */
export function weatherDescribe(code: number): { label: string; icon: LucideIcon } {
    if (code <= 1) return { label: code === 0 ? 'Clear' : 'Mostly clear', icon: Sun };
    if (code === 2) return { label: 'Partly cloudy', icon: CloudSun };
    if (code === 3) return { label: 'Overcast', icon: Cloud };
    if ([45, 48].includes(code)) return { label: 'Fog', icon: CloudFog };
    if ([51, 53, 55, 56, 57].includes(code)) return { label: 'Drizzle', icon: CloudDrizzle };
    if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return { label: 'Rain', icon: CloudRain };
    if ([71, 73, 75, 77, 85, 86].includes(code)) return { label: 'Snow', icon: CloudSnow };
    if ([95, 96, 99].includes(code)) return { label: 'Storm', icon: CloudLightning };
    return { label: 'Clear', icon: Sun };
}
