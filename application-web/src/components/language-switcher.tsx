import { useTranslation } from 'react-i18next'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { supportedLanguages } from '@/i18n'

const languageLabels: Record<string, string> = { en: 'English', et: 'Eesti' }

export function LanguageSwitcher() {
  const { i18n } = useTranslation()
  return (
    <Select value={i18n.language} onValueChange={(lng) => i18n.changeLanguage(lng)}>
      <SelectTrigger size="sm" className="w-[110px]" aria-label={i18n.t('common.languageSwitcherLabel')}>
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {supportedLanguages.map((lng) => (
          <SelectItem key={lng} value={lng}>{languageLabels[lng]}</SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
