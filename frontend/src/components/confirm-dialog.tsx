import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from "react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { useT } from "@/i18n/use-locale";

/** FSEC-13: accessible replacement for window.confirm / window.prompt. */
export interface ConfirmOptions {
  title: string;
  description?: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  /** Red confirm button for destructive actions. */
  destructive?: boolean;
  /** Collect text (replaces window.prompt). */
  input?: { label: string; placeholder?: string; required?: boolean; defaultValue?: string; readOnly?: boolean };
  testId?: string;
}

interface ConfirmDialogProps extends ConfirmOptions {
  open: boolean;
  onResolve: (confirmed: boolean, value: string) => void;
}

export function ConfirmDialog({
  open, title, description, confirmLabel, cancelLabel, destructive, input, testId = "dialog-confirm", onResolve,
}: ConfirmDialogProps) {
  const t = useT();
  const [value, setValue] = useState(input?.defaultValue ?? "");
  const blocked = !!input?.required && value.trim() === "";

  return (
    <AlertDialog open={open} onOpenChange={(o) => { if (!o) onResolve(false, ""); }}>
      <AlertDialogContent data-testid={testId}>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription asChild={typeof description !== "string"}>
            {typeof description === "string" || description === undefined ? description : <div>{description}</div>}
          </AlertDialogDescription>
        </AlertDialogHeader>
        {input && (
          <div className="space-y-2">
            <Label htmlFor={`${testId}-input`}>{input.label}</Label>
            <Input
              id={`${testId}-input`}
              autoFocus
              value={value}
              readOnly={input.readOnly}
              placeholder={input.placeholder}
              onChange={(e) => setValue(e.target.value)}
              onFocus={(e) => input.readOnly && e.currentTarget.select()}
              onKeyDown={(e) => { if (e.key === "Enter" && !blocked) { e.preventDefault(); onResolve(true, value.trim()); } }}
              data-testid={`${testId}-input`}
            />
          </div>
        )}
        <AlertDialogFooter>
          <AlertDialogCancel data-testid={`${testId}-cancel`}>{cancelLabel ?? t("common.cancel")}</AlertDialogCancel>
          <AlertDialogAction
            disabled={blocked}
            className={cn(destructive && buttonVariants({ variant: "destructive" }))}
            onClick={(e) => { e.preventDefault(); onResolve(true, value.trim()); }}
            data-testid={`${testId}-confirm`}
          >
            {confirmLabel ?? t("common.confirm")}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}

interface ConfirmApi {
  /** Resolves true when the user confirms. */
  confirm: (opts: ConfirmOptions) => Promise<boolean>;
  /** Resolves the entered text, or null when cancelled. */
  prompt: (opts: ConfirmOptions & { input: NonNullable<ConfirmOptions["input"]> }) => Promise<string | null>;
}

const ConfirmContext = createContext<ConfirmApi | null>(null);

export function ConfirmProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<(ConfirmOptions & { seq: number }) | null>(null);
  const resolver = useRef<((confirmed: boolean, value: string) => void) | null>(null);
  const counter = useRef(0);

  const ask = useCallback((opts: ConfirmOptions) => new Promise<{ confirmed: boolean; value: string }>((resolve) => {
    // A second request while one is open cancels the first.
    resolver.current?.(false, "");
    resolver.current = (confirmed, value) => { resolver.current = null; setState(null); resolve({ confirmed, value }); };
    counter.current += 1;
    setState({ ...opts, seq: counter.current });
  }), []);

  const api = useMemo<ConfirmApi>(() => ({
    confirm: async (opts) => (await ask(opts)).confirmed,
    prompt: async (opts) => {
      const r = await ask(opts);
      return r.confirmed ? r.value : null;
    },
  }), [ask]);

  return (
    <ConfirmContext.Provider value={api}>
      {children}
      {state && <ConfirmDialog key={state.seq} {...state} open onResolve={(c, v) => resolver.current?.(c, v)} />}
    </ConfirmContext.Provider>
  );
}

export function useConfirm(): ConfirmApi {
  const ctx = useContext(ConfirmContext);
  if (!ctx) throw new Error("useConfirm must be used inside <ConfirmProvider>");
  return ctx;
}
