import { ThemeProvider } from '@/components/theme-provider'
import i18n from '@/i18n'
import { I18nextProvider } from 'react-i18next'
import { BrowserRouter } from 'react-router'
import Routing from './Routing'
import {
  QueryClient,
  QueryClientProvider,
} from '@tanstack/react-query'

const queryClient = new QueryClient()

function App() {
  return (
    <>
      <QueryClientProvider client={queryClient}>
        <I18nextProvider i18n={i18n}>
          <BrowserRouter>
            <ThemeProvider defaultTheme='light' storageKey='ui-theme'>
              {/* <div className="fixed top-4 right-4 z-50">
                <LanguageSwitcher />
              </div> */}
              <Routing />
            </ThemeProvider>
          </BrowserRouter>
        </I18nextProvider>
      </QueryClientProvider>
    </>
  )
}

export default App
