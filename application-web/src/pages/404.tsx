import { Link } from "react-router"
import { useTranslation } from "react-i18next"

const NotFoundPage = () => {
  const { t } = useTranslation()

  return (
    <>
      <h1>
        {t('notFound.title')}
      </h1>
      <Link to="/">
        {t('notFound.backToHome')}
      </Link>
    </>
  )
}

export default NotFoundPage