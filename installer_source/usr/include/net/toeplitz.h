/*-
 * Copyright (c) 2010 David Malone <dwmalone@FreeBSD.org>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */

#ifndef _NETINET_TOEPLITZ_H_
#define	_NETINET_TOEPLITZ_H_

#if 0 /* XXXNETGATE */
/*
 * Toeplitz (RSS) hash algorithm; possibly we should cache intermediate
 * results between runs, in which case we'll need explicit init/destroy and
 * state management.
 */
uint32_t	toeplitz_hash(u_int keylen, const uint8_t *key,
		    u_int datalen, const uint8_t *data);

#else

#include <sys/endian.h>

/* Toeplitz hash from DPDK */
static inline uint32_t
toeplitz_hash(u_int keylen, const uint8_t *rss_key, u_int datalen,
              const uint8_t *data) {
	uint32_t i, j, map, ret = 0;

	datalen /= 4;
	for (j = 0; j < datalen; j++) {
		map = (data[0]<<24) + (data[1]<<16) + (data[2] <<8) + data[3];
		data += 4;
		for ( ; map; map &= (map - 1)) {
			/* Get the count of trailing 0-bits in map */
			i = (uint32_t)__builtin_ctz(map);

			ret ^= ((const uint32_t *)rss_key)[j] << (31 - i) |
                                (uint32_t)((uint64_t)(((const uint32_t *)rss_key)[j + 1]) >> (i + 1));
                }
        }
        return ret;
}
#endif /* XXX NETGATE */

#endif /* !_NETINET_TOEPLITZ_H_ */
