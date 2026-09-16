/**
 * Tailles maximales d'un quiz, identiques aux contraintes du backend
 * (QuizInput et QuestionInput) : le formulaire bloque la saisie avant que le serveur refuse.
 */
export const QUIZ_LIMITS = {
  title: 180,
  description: 1000,
  category: 60,
  questions: 100,
  questionText: 500,
  choices: 6,
  choiceText: 200,
  explanation: 1000,
}
