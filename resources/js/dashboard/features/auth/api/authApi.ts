import { apiRequest } from '../../../lib/api';
import type { MeResponse } from '../../../types';

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface LoginPayload {
  email: string;
  password: string;
}

export function register(payload: RegisterPayload): Promise<void> {
  return apiRequest('/register', { method: 'POST', body: payload });
}

export function login(payload: LoginPayload): Promise<void> {
  return apiRequest('/login', { method: 'POST', body: payload });
}

export function logout(): Promise<void> {
  return apiRequest('/logout', { method: 'POST' });
}

export function resendVerificationEmail(): Promise<void> {
  return apiRequest('/email/verification-notification', { method: 'POST' });
}

export function me(): Promise<MeResponse> {
  return apiRequest('/api/me');
}
