function getColor(providerType: string, score: number | null): string {
  switch (providerType) {
    case 'NT':
      return 'bg-slate-600'
    case 'MT':
      return 'bg-indigo-600'
    case 'TM':
      if (!score) return ''
      if (score === 101) return 'bg-sky-600'
      if (score >= 90) return 'bg-emerald-600'
      if (score >= 70) return 'bg-amber-600'
      if (score >= 50) return 'bg-orange-600'
      return 'bg-red-600'
    default:
      return ''
  }
}

const SuggestionBadge = ({ providerType, score, className }: { providerType: string | null, score: number | null, className?: string }) => {
  if (!providerType) return null

  return (
    <div className={`w-8 flex flex-col items-center justify-center text-gray-100 ${getColor(providerType, score)} ${className ?? ''}`}>
      {!!score && <span className="font-semibold">{Math.round(score)}</span>}
      {providerType !== 'TM' && <span>{providerType}</span>}
    </div>
  )
}

export default SuggestionBadge
