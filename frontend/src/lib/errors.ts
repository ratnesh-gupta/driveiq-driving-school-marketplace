import { translate } from "@/i18n";
import { useLocaleStore } from "@/i18n/use-locale";

/** HTTP failure from one of the hand-written clients; `message` is already safe to show. */
export class HttpError extends Error {
  readonly status: number;

  constructor(status: number, message: string) {
    super(message);
    this.name = "HttpError";
    this.status = status;
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
