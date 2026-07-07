import { ThemeProvider } from '@/components/theme-provider'
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
        <BrowserRouter>
          <ThemeProvider defaultTheme='light' storageKey='ui-theme'>
            <Routing />
          </ThemeProvider>
        </BrowserRouter>
      </QueryClientProvider>
    </>
  )
}

export default App
