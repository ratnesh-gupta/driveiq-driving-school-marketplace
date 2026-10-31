import { translate } from "@/i18n";
import { useLocaleStore } from "@/i18n/use-locale";

/** HTTP failure from one of the hand-written clients; `message` is already safe to show. */
export class HttpError extends Error {
  readonly status: number;
  /** Correlation id from the error body (`requestId`), for support. */
  readonly requestId?: string;
  /** Machine code from the error body (e.g. `verification_required`). */
  readonly code?: string;
  /** Extra safe-to-read fields of 4xx bodies (e.g. `blockers`). */
  readonly blockers?: string[];

  constructor(status: number, message: string, extra: { requestId?: string; code?: string; blockers?: string[] } = {}) {
    super(message);
    this.name = "HttpError";
    this.status = status;
    this.requestId = extra.requestId;
    this.code = extra.code;
    this.blockers = extra.blockers;
  }
}

/** Generic, translated text for 5xx responses (FSEC-07). */
export function genericServerMessage(): string {
  return translate(useLocaleStore.getState().locale, "common.serverError");
}

/**
 * Message safe to show a user. Server text is shown only for 4xx (validation,
 * business rules); 5xx may carry debug output, so it is replaced.
 */
export function safeErrorMessage(status: number, serverMessage: string | undefined, fallback: string): string {
  if (status >= 500) return genericServerMessage();
  return serverMessage || fallback;
}

/** Pull requestId / code / blockers out of a parsed error body (anything else is ignored). */
export function errorExtras(data: unknown): { requestId?: string; code?: string; blockers?: string[] } {
  if (!data || typeof data !== "object") return {};
  const d = data as Record<string, unknown>;
  return {
    requestId: typeof d.requestId === "string" ? d.requestId.slice(0, 64) : undefined,
    code: typeof d.code === "string" ? d.code.slice(0, 64) : undefined,
    blockers: Array.isArray(d.blockers) ? d.blockers.filter((b): b is string => typeof b === "string").slice(0, 20) : undefined,
  };
}

/** "Reference: xxxx" line for 5xx errors that carry a requestId, else "". */
export function errorReference(err: unknown): string {
  if (err instanceof HttpError && err.status >= 500 && err.requestId) {
    return translate(useLocaleStore.getState().locale, "common.reference", { id: err.requestId });
  }
  return "";
}

/** Safe message plus, for 5xx, a small reference line. Use in toasts and inline errors. */
export function errorMessageWithReference(err: unknown, fallback: string): string {
  const base = err instanceof Error ? err.message : fallback;
  const ref = errorReference(err);
  return ref ? `${base} ${ref}` : base;
}
