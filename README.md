# marketCurly clone coding

API List
```
1	POST	/jwt	jwt발급
2	GET	/jwt	jwt 유효성 검사
3	GET	/	(test)
4	GET	/users	(test) 모든 유저 정보 조회
5	POST	/user	유저 정보 등록 (회원 가입) 
6	GET	/user	로그인된 user 정보 조회
7	PATCH	/user	로그인된 user 정보 수정
8	DELETE	/user	로그인된 user 정보 삭제
9	GET	/coupons	로그인된 user의 모든 coupon 정보 조회
10	GET	/orders	로그인된 user의 모든 주문 정보 조회
11	POST	/order	주문 생성
12	DELETE	/order/{order_id}	주문 삭제
13	GET	/order/{order_id}	주문 내역 상세 조회
14	GET	/categories	전체 카테고리 리스트 조회
15	GET	categories/{category_id}/subcategories	특정 카테고리의 서브카테고리 리스트 조회
16	GET	/products	상품 리스트 조회
17	GET	/product/{product_id}/options	특정 상품의 모든 옵션 조회
18	GET	/product/{product_id}/img	특정 상품의 상세이미지(영양성분표) 조회
19	GET	/product/{product_id}/description	특정 상품의 상품설명 조회
20	POST	/product	상품 등록
21	DELETE	/product/{product_id}	상품 삭제
22	DELETE	/product/option/{option_id}	상품 옵션 삭제
23	POST	/product/option/{option_id}/review	상품 후기 작성
24	PATCH	/product/option/review/{review_id}	상품 후기 수정
25	GET	/product/{product_id}/reviews	상품 후기 리스트 조회
26	GET	/product/review/{review_id}	상품 후기 상세 보기
27	POST	/kakao-sign-in	카카오 소셜 로그인
```
