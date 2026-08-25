import { createContext, useCallback, useContext, useState } from "react"
import { useTranslation } from "react-i18next"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"

type ConfirmOptions = {
  title: string
  description?: string
  confirmText?: string
  cancelText?: string
  variant?: "default" | "destructive"
  /** When set, shows a "don't ask again this session" checkbox; checking it
   *  auto-confirms future confirm() calls that share this key, for the
   *  browser session (sessionStorage). */
  rememberKey?: string
  rememberLabel?: string
}

type ConfirmContextValue = (options: ConfirmOptions) => Promise<boolean>

const ConfirmContext = createContext<ConfirmContextValue | undefined>(undefined)

const skipStorageKey = (rememberKey: string) => `confirm-skip:${rememberKey}`

export function ConfirmProvider({ children }: { children: React.ReactNode }) {
  const { t } = useTranslation()
  const [pending, setPending] = useState<{ options: ConfirmOptions, resolve: (v: boolean) => void } | null>(null)
  const [remember, setRemember] = useState(false)

  const confirm = useCallback<ConfirmContextValue>((options) => {
    if (options.rememberKey && sessionStorage.getItem(skipStorageKey(options.rememberKey)) === 'true') {
      return Promise.resolve(true)
    }
    return new Promise<boolean>((resolve) => {
      setRemember(false)
      setPending({ options, resolve })
    })
  }, [])

  const close = (result: boolean) => {
    if (!pending) return
    if (result && remember && pending.options.rememberKey) {
      sessionStorage.setItem(skipStorageKey(pending.options.rememberKey), 'true')
    }
    pending.resolve(result)
    setPending(null)
  }

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      <Dialog open={!!pending} onOpenChange={(open) => !open && close(false)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{pending?.options.title}</DialogTitle>
            {pending?.options.description && (
              <DialogDescription>{pending.options.description}</DialogDescription>
            )}
          </DialogHeader>
          {pending?.options.rememberKey && (
            <div className="flex items-center gap-2">
              <Checkbox id="confirm-remember" checked={remember} onCheckedChange={(c) => setRemember(c === true)} />
              <label htmlFor="confirm-remember" className="text-sm">
                {pending.options.rememberLabel ?? t('common.dontAskAgainSession')}
              </label>
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => close(false)}>
              {pending?.options.cancelText ?? t('common.cancel')}
            </Button>
            <Button variant={pending?.options.variant === 'destructive' ? 'destructive' : 'default'} onClick={() => close(true)}>
              {pending?.options.confirmText ?? t('common.confirm')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </ConfirmContext.Provider>
  )
}

export const useConfirm = () => {
  const context = useContext(ConfirmContext)

  if (context === undefined)
    throw new Error("useConfirm must be used within a ConfirmProvider")

  return context
}
