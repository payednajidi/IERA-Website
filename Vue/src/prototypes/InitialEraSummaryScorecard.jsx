import React from 'react'

/**
 * Thresholds for quantitative trigger.
 * A task needs advanced ERA when:
 * 1) score >= threshold, OR
 * 2) any pain body part is flagged for the same factor.
 */
const DEFAULT_FACTORS = [
  { key: 'awkwardPosture', label: 'Awkward Posture', threshold: 6 },
  { key: 'forcefulExertion', label: 'Forceful Exertion', threshold: 1 },
  { key: 'vibration', label: 'Vibration', threshold: 1 },
  { key: 'noise', label: 'Noise', threshold: 1 },
  { key: 'lighting', label: 'Lighting', threshold: 1 },
  { key: 'temperature', label: 'Temperature', threshold: 1 },
  { key: 'ventilation', label: 'Ventilation', threshold: 1 },
]

const getVerdict = ({ score, threshold, painParts }) => {
  const hasPainTrigger = Array.isArray(painParts) && painParts.length > 0
  const hasScoreTrigger = Number(score ?? 0) >= Number(threshold ?? 0)
  const needAdvancedEra = hasScoreTrigger || hasPainTrigger

  return {
    hasPainTrigger,
    hasScoreTrigger,
    needAdvancedEra,
  }
}

const ForcefulBreakdown = ({ breakdown }) => {
  if (!breakdown || typeof breakdown !== 'object') return <span>-</span>

  const rows = Object.entries(breakdown)
  if (!rows.length) return <span>-</span>

  return (
    <div className="space-y-1 text-xs">
      {rows.map(([name, value]) => (
        <div key={name} className="flex items-center justify-between gap-3">
          <span className="text-slate-600">{name}</span>
          <span className="min-w-6 text-right font-semibold">{String(value)}</span>
        </div>
      ))}
    </div>
  )
}

const VerdictBadge = ({ yes }) => (
  <span
    className={[
      'inline-flex min-w-16 items-center justify-center rounded-md px-2 py-1 text-xs font-bold tracking-wide',
      yes ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white',
    ].join(' ')}
  >
    {yes ? 'YES' : 'NO'}
  </span>
)

/**
 * tasks shape:
 * [
 *   {
 *     id: 'task-1',
 *     name: 'Forklift Driver',
 *     scores: {
 *       awkwardPosture: 13,
 *       forcefulExertion: 1,
 *       vibration: 4,
 *       noise: 2,
 *       lighting: 0,
 *       temperature: 0,
 *       ventilation: 0,
 *       forcefulBreakdown: {
 *         'Lifting/Lowering': 1,
 *         'Pushing/Pulling': 0,
 *       },
 *     },
 *     painFlags: {
 *       awkwardPosture: ['Neck', 'Shoulder'],
 *       forcefulExertion: ['Lower Back'],
 *       vibration: [],
 *       noise: [],
 *       lighting: [],
 *       temperature: [],
 *       ventilation: [],
 *     },
 *   },
 * ]
 */
export default function InitialEraSummaryScorecard({
  title = 'Initial ERA Form',
  factors = DEFAULT_FACTORS,
  tasks = [],
}) {
  return (
    <section className="w-full rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
      <h2 className="mb-4 text-center text-2xl font-bold text-slate-900">{title}</h2>

      <div className="overflow-x-auto">
        <table className="min-w-[1100px] w-full border-collapse text-sm">
          <thead>
            <tr className="bg-slate-100">
              <th className="border border-slate-300 p-2 text-left font-bold">Risk Factor</th>
              <th className="border border-slate-300 p-2 text-center font-bold">Minimum Requirement</th>
              {tasks.map((task) => (
                <th
                  key={`head-${task.id}`}
                  colSpan={3}
                  className="border border-slate-300 bg-slate-800 p-2 text-center font-bold text-white"
                >
                  {task.name}
                </th>
              ))}
            </tr>

            <tr className="bg-slate-50 text-xs">
              <th className="border border-slate-300 p-2" />
              <th className="border border-slate-300 p-2" />
              {tasks.map((task) => (
                <React.Fragment key={`sub-${task.id}`}>
                  <th className="border border-slate-300 p-2 font-bold">Result (Score)</th>
                  <th className="border border-slate-300 p-2 font-bold">Pain Trigger (Body Parts)</th>
                  <th className="border border-slate-300 p-2 font-bold">Need Advanced ERA</th>
                </React.Fragment>
              ))}
            </tr>
          </thead>

          <tbody>
            {factors.map((factor) => (
              <tr key={factor.key} className="align-top odd:bg-white even:bg-slate-50">
                <td className="border border-slate-300 p-2 font-semibold text-slate-900">{factor.label}</td>
                <td className="border border-slate-300 p-2 text-center font-semibold">
                  {'>= '}
                  {factor.threshold}
                </td>

                {tasks.map((task) => {
                  const score = task?.scores?.[factor.key] ?? 0
                  const painParts = task?.painFlags?.[factor.key] ?? []
                  const verdict = getVerdict({
                    score,
                    threshold: factor.threshold,
                    painParts,
                  })

                  return (
                    <React.Fragment key={`factor-${factor.key}-${task.id}`}>
                      <td className="border border-slate-300 p-2 text-center font-bold">
                        {factor.key === 'forcefulExertion' ? (
                          <div className="space-y-1">
                            <div>{Number(score)}</div>
                            <ForcefulBreakdown breakdown={task?.scores?.forcefulBreakdown} />
                          </div>
                        ) : (
                          Number(score)
                        )}
                      </td>

                      <td className="border border-slate-300 p-2">
                        {verdict.hasPainTrigger ? (
                          <ul className="list-disc space-y-1 pl-4 text-xs text-slate-700">
                            {painParts.map((part) => (
                              <li key={`${task.id}-${factor.key}-${part}`}>{part}</li>
                            ))}
                          </ul>
                        ) : (
                          <span className="text-xs text-slate-500">None</span>
                        )}
                      </td>

                      <td className="border border-slate-300 p-2 text-center">
                        <VerdictBadge yes={verdict.needAdvancedEra} />
                      </td>
                    </React.Fragment>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}

export const sampleTasks = [
  {
    id: 'forklift-driver',
    name: 'Forklift Driver',
    scores: {
      awkwardPosture: 13,
      forcefulExertion: 0,
      vibration: 4,
      noise: 2,
      lighting: 0,
      temperature: 0,
      ventilation: 0,
      forcefulBreakdown: {
        'Lifting/Lowering': 0,
        'Pushing/Pulling': 0,
        'Handling Seated': 0,
        Carrying: 0,
      },
    },
    painFlags: {
      awkwardPosture: ['Neck', 'Shoulder', 'Lower Back'],
      forcefulExertion: [],
      vibration: [],
      noise: [],
      lighting: [],
      temperature: [],
      ventilation: [],
    },
  },
  {
    id: 'logistic-worker',
    name: 'Logistic Worker',
    scores: {
      awkwardPosture: 13,
      forcefulExertion: 2,
      vibration: 0,
      noise: 0,
      lighting: 0,
      temperature: 0,
      ventilation: 0,
      forcefulBreakdown: {
        'Lifting/Lowering': 1,
        'Pushing/Pulling': 1,
        'Handling Seated': 0,
        Carrying: 0,
      },
    },
    painFlags: {
      awkwardPosture: ['Neck', 'Shoulder'],
      forcefulExertion: ['Lower Back'],
      vibration: [],
      noise: [],
      lighting: [],
      temperature: [],
      ventilation: [],
    },
  },
]
