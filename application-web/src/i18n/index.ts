import i18next from 'i18next'
import { initReactI18next } from 'react-i18next'
import en from './locales/en/common.json'
import et from './locales/et/common.json'

export const LANGUAGE_STORAGE_KEY = 'ui-language'
export const supportedLanguages = ['en', 'et'] as const
export type SupportedLanguage = typeof supportedLanguages[number]

const getInitialLanguage = (): SupportedLanguage => {
  // const stored = localStorage.getItem(LANGUAGE_STORAGE_KEY)
  // if (stored && (supportedLanguages as readonly string[]).includes(stored)) return stored as SupportedLanguage
  // const browserLang = navigator.language.slice(0, 2)
  // return (supportedLanguages as readonly string[]).includes(browserLang) ? browserLang as SupportedLanguage : 'et'
  return 'et'
}

i18next.use(initReactI18next).init({
  resources: { en: { common: en }, et: { common: et } },
  lng: getInitialLanguage(),
  fallbackLng: 'et',
  defaultNS: 'common',
  ns: ['common'],
  interpolation: { escapeValue: false },
  react: { useSuspense: false },
})

document.documentElement.lang = i18next.language

i18next.on('languageChanged', (lng) => {
  localStorage.setItem(LANGUAGE_STORAGE_KEY, lng)
  document.documentElement.lang = lng
})

export default i18next
