import { Route, Routes } from "react-router"

import TranslationMemoryEditPage from "./pages/translation-memory-edit/translation-memory-edit"
import NotFoundPage from "./pages/404"

const Routing = () => {
  return (
    <>
      <Routes>
        <Route path="/translation-memories/:translation_memory_id/edit" element={<TranslationMemoryEditPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </>
  )
}

export default Routing