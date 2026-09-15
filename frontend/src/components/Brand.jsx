import logoMark from '../assets/logo-mark.svg'

/** Logo QuizLab : la bulle (on échange) dont le Q se termine en coche (on apprend). */
export default function Brand() {
  return (
    <>
      <img className="brand-mark" src={logoMark} alt="" width="34" height="34" />
      <span className="brand-name">
        Quiz<em>Lab</em>
      </span>
    </>
  )
}
