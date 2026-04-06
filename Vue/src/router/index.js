import { createRouter, createWebHistory } from 'vue-router'

import DashboardLayout from '../layouts/DashboardLayout.vue'
import EraForm from '../views/EraForm.vue'
import EraChecklist from '../views/EraChecklist.vue'
import EraForcefulExertion from '../views/EraForcefulExertion.vue'
import EraRepetitiveMotion from '../views/EraRepetitiveMotion.vue'
import EraVibration from '../views/EraVibration.vue'
import EraEnvironmentalFactors from '../views/EraEnvironmentalFactors.vue'
import EraSummary from '../views/EraSummary.vue'
import EraAssessmentFileEditPreview from '../views/EraAssessmentFileEditPreview.vue'
import EraAssessmentTotalInformation from '../views/EraAssessmentTotalInformation.vue'

const routes = [

{
path:'/',
component:DashboardLayout,
children:[

{
path:'era-form',
component:EraForm
},

{
path:'era-checklist/:assessmentId',
component:EraChecklist
},

{
path:'era-forceful-exertion/:assessmentId',
component:EraForcefulExertion
},

{
path:'era-repetitive-motion/:assessmentId',
component:EraRepetitiveMotion
},

{
path:'era-vibration/:assessmentId',
component:EraVibration
},

{
path:'era-environmental-factors/:assessmentId',
component:EraEnvironmentalFactors
},

{
path:'era-summary/:assessmentId',
component:EraSummary
},

{
path:'era-assessment-files',
name:'era-assessment-files',
component:EraAssessmentFileEditPreview
},

{
path:'era-assessment-files/:assessmentId/total-information',
name:'era-assessment-files-total-information',
component:EraAssessmentTotalInformation
}

]
}

]

export default createRouter({
history:createWebHistory(),
routes
})
