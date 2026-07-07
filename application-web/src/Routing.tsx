import { Route, Routes } from "react-router"

import TranslationMemoryEditPage from "./pages/translation-memory-edit/translation-memory-edit"

const Routing = () => {
  return (
    <>
      <Routes>
        <Route path="/translation-memories/:translation_memory_id/edit" element={<TranslationMemoryEditPage />} />
        <Route path="*" element={<h1>404</h1>} />
      </Routes>
    </>
  )
}

export default Routing