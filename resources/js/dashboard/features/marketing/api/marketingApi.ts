import { apiRequest } from '../../../lib/api';
export interface Campaign { id:string; name:string; channel:string; objective:string; status:string; budget:string|null; currency:string; starts_at:string|null; ends_at:string|null; message:string|null; created_at:string|null }
export interface CampaignPayload { name:string; channel:string; objective:string; status:string; budget:number|null; starts_at:string|null; ends_at:string|null; content:{message:string} }
export function listCampaigns():Promise<{data:Campaign[];summary:{total:number;active:number;scheduled:number;attribution_available:boolean}}>{return apiRequest('/api/v1/marketing/campaigns')}
export function createCampaign(payload:CampaignPayload):Promise<{data:Campaign}>{return apiRequest('/api/v1/marketing/campaigns',{method:'POST',body:payload})}
export function updateCampaign(id:string,payload:Partial<CampaignPayload>):Promise<{data:Campaign}>{return apiRequest(`/api/v1/marketing/campaigns/${id}`,{method:'PATCH',body:payload})}
export function deleteCampaign(id:string):Promise<void>{return apiRequest(`/api/v1/marketing/campaigns/${id}`,{method:'DELETE'})}
