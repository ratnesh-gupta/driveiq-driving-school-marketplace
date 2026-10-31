/**
 * Renders schema.org structured data (DIQ-504). "<", ">", "&" and the line
 * separators U+2028/2029 are escaped so values such as a school name can never
 * close the script tag or break the JSON (FSEC-09).
 */
const ESCAPES: Record<string, string> = {
  "<": "\\u003c",
  ">": "\\u003e",
  "&": "\\u0026",
  "\u2028": "\\u2028",
  "\u2029": "\\u2029",
};

export function JsonLd({ data }: { data: Record<string, unknown> }) {
  const json = JSON.stringify(data).replace(/[<>&\u2028\u2029]/g, (c) => ESCAPES[c]);
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: json }} />;
}

export const SITE_ORIGIN = typeof window !== "undefined" ? window.location.origin : "";

export function breadcrumbList(items: { name: string; path: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, i) => ({
      "@type": "ListItem",
      position: i + 1,
      name: item.name,
      item: `${SITE_ORIGIN}${item.path}`,
    })),
  };
}
